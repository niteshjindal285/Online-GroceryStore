<?php
/**
 * Project Management Service – Extended
 * Full CRUD for projects, phases, and invoices.
 */

require_once __DIR__ . '/database.php';

function project_invoice_table_ready() {
    return db_table_exists('project_invoices');
}

// ─────────────────────────────────────────────────────────
// PROJECT CRUD
// ─────────────────────────────────────────────────────────

/**
 * Get all projects for a company (with summary stats)
 */
function project_get_all($company_id = null) {
    $company_id = $company_id ?? ($_SESSION['company_id'] ?? 1);
    $invoice_join = '';
    $paid_amount_sql = '0';

    if (project_invoice_table_ready()) {
        $invoice_join = "LEFT JOIN project_invoices pi ON pi.project_id = p.id";
        $paid_amount_sql = "COALESCE(SUM(CASE WHEN pi.status = 'paid' THEN pi.total_amount ELSE 0 END), 0)";
    }

    return db_fetch_all("
        SELECT p.*,
               c.name  AS customer_name,
               u.username AS created_by_name,
               COUNT(DISTINCT ps.id) AS phase_count,
               COALESCE(SUM(CASE WHEN ps.status IN ('invoiced','paid') THEN ps.amount ELSE 0 END), 0) AS invoiced_amount,
               {$paid_amount_sql} AS paid_amount
        FROM projects p
        LEFT JOIN customers c       ON p.customer_id  = c.id
        LEFT JOIN users u           ON p.created_by   = u.id
        LEFT JOIN project_stages ps ON ps.project_id  = p.id
        {$invoice_join}
        WHERE p.company_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
    ", [$company_id]);
}

/**
 * Get single project by ID
 */
function project_get_by_id($project_id) {
    return db_fetch("
        SELECT p.*,
               c.name  AS customer_name,
               u.username AS created_by_name
        FROM projects p
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN users u     ON p.created_by  = u.id
        WHERE p.id = ?
    ", [$project_id]);
}

/**
 * Create a project (with optional phases array)
 */
function project_create_with_stages($data, $userId) {
    $name       = trim($data['project_name'] ?? '');
    $desc       = trim($data['project_description'] ?? '');
    $customerId = intval($data['customer_id'] ?? 0);
    $totalValue = floatval($data['project_total_value'] ?? 0);
    $companyId  = $_SESSION['company_id'] ?? 1;
    $startDate  = $data['start_date'] ?? null;
    $endDate    = $data['end_date'] ?? null;
    $manager    = trim($data['project_manager'] ?? '');

    if (empty($name) || $customerId <= 0) return false;

    // Auto-generate project number (using 'code' column)
    $seq = db_fetch("SELECT COUNT(*) AS cnt FROM projects")['cnt'] ?? 0;
    $projectCode = 'PRJ-' . str_pad($seq + 1, 5, '0', STR_PAD_LEFT);

    $projectId = db_insert(
        "INSERT INTO projects (name, description, customer_id, total_value, created_by, company_id, code, start_date, end_date, project_manager)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$name, $desc, $customerId, $totalValue, $userId, $companyId, $projectCode, $startDate ?: null, $endDate ?: null, $manager ?: null]
    );

    if (!$projectId) return false;

    // Insert Stages / Phases
    $stageNames    = $data['stage_name']    ?? [];
    $stagePercents = $data['stage_percent'] ?? [];
    $stageAmounts  = $data['stage_amount']  ?? [];
    $stageDetails  = $data['stage_details'] ?? [];
    $stageDueDates = $data['stage_due_date'] ?? [];

    $sql = "INSERT INTO project_stages (project_id, stage_name, percentage, amount, details, due_date, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $createdStageIds = [];

    for ($i = 0; $i < count($stageNames); $i++) {
        if (!empty($stageNames[$i])) {
            $sid = db_insert($sql, [
                $projectId,
                $stageNames[$i],
                floatval($stagePercents[$i] ?? 0),
                floatval($stageAmounts[$i] ?? 0),
                $stageDetails[$i] ?? null,
                !empty($stageDueDates[$i]) ? $stageDueDates[$i] : null,
                $i
            ]);
            $createdStageIds[] = $sid;
        }
    }

    return ['project_id' => $projectId, 'stage_ids' => $createdStageIds, 'code' => $projectCode];
}

/**
 * Update project status
 */
function project_update_status($project_id, $status) {
    return db_query("UPDATE projects SET status = ? WHERE id = ?", [$status, $project_id]);
}

// ─────────────────────────────────────────────────────────
// PHASE (STAGE) CRUD
// ─────────────────────────────────────────────────────────

/**
 * Get all stages for a project (sorted)
 */
function project_get_stages($projectId) {
    return db_fetch_all("SELECT * FROM project_stages WHERE project_id = ? ORDER BY sort_order ASC, id ASC", [$projectId]);
}

/**
 * Get a single phase by ID
 */
function project_get_phase($phase_id) {
    return db_fetch("SELECT * FROM project_stages WHERE id = ?", [$phase_id]);
}

/**
 * Add a new phase to an existing project
 */
function project_add_phase($data, $project_id) {
    $sort = db_fetch("SELECT COALESCE(MAX(sort_order),0)+1 AS nxt FROM project_stages WHERE project_id=?", [$project_id])['nxt'] ?? 1;
    return db_insert(
        "INSERT INTO project_stages (project_id, stage_name, percentage, amount, details, due_date, sort_order) VALUES (?,?,?,?,?,?,?)",
        [
            $project_id,
            trim($data['stage_name']),
            floatval($data['percentage'] ?? 0),
            floatval($data['amount'] ?? 0),
            trim($data['details'] ?? ''),
            !empty($data['due_date']) ? $data['due_date'] : null,
            $sort
        ]
    );
}

/**
 * Update phase status
 */
function project_update_phase_status($phase_id, $status) {
    $valid = ['pending','in_progress','complete','invoiced','paid'];
    if (!in_array($status, $valid)) return false;
    return db_query("UPDATE project_stages SET status = ? WHERE id = ?", [$status, $phase_id]);
}

/**
 * Delete a phase
 */
function project_delete_phase($phase_id) {
    return db_query("DELETE FROM project_stages WHERE id = ?", [$phase_id]);
}

// ─────────────────────────────────────────────────────────
// INVOICE CRUD
// ─────────────────────────────────────────────────────────

/**
 * Get all invoices for a project
 */
function project_get_invoices($project_id) {
    if (!project_invoice_table_ready()) {
        return [];
    }

    return db_fetch_all("
        SELECT pi.*, ps.stage_name, u.username AS created_by_name
        FROM project_invoices pi
        LEFT JOIN project_stages ps ON pi.stage_id = ps.id
        LEFT JOIN users u           ON pi.created_by = u.id
        WHERE pi.project_id = ?
        ORDER BY pi.created_at DESC
    ", [$project_id]);
}

/**
 * Create a project invoice for a specific stage
 */
function project_create_invoice($project_id, $stage_id, $data, $user_id) {
    if (!project_invoice_table_ready()) {
        throw new Exception('Project invoice table is missing. Run the database repair script first.');
    }

    // Auto-generate invoice number
    $seq = db_fetch("SELECT COUNT(*) AS cnt FROM project_invoices")['cnt'] ?? 0;
    $inv_num = 'PINV-' . str_pad($seq + 1, 5, '0', STR_PAD_LEFT);

    $amount     = floatval($data['amount'] ?? 0);
    $tax        = floatval($data['tax_amount'] ?? 0);
    $total      = $amount + $tax;

    $id = db_insert("
        INSERT INTO project_invoices (project_id, stage_id, invoice_number, amount, tax_amount, total_amount, status, issued_date, due_date, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?)
    ", [
        $project_id, $stage_id ?: null, $inv_num, $amount, $tax, $total,
        $data['issued_date'] ?? date('Y-m-d'),
        $data['due_date'] ?? null,
        trim($data['notes'] ?? ''),
        $user_id
    ]);

    if ($id && $stage_id) {
        // Mark phase as invoiced
        db_query("UPDATE project_stages SET status='invoiced', invoice_number=?, invoiced_at=NOW() WHERE id=?", [$inv_num, $stage_id]);
    }

    return $id ? ['id' => $id, 'invoice_number' => $inv_num] : false;
}

/**
 * Update invoice status (draft → sent → paid → cancelled)
 */
function project_update_invoice_status($invoice_id, $status) {
    if (!project_invoice_table_ready()) {
        return false;
    }

    $valid = ['draft','sent','paid','cancelled'];
    if (!in_array($status, $valid)) return false;

    $extra = '';
    $params = [$status, $invoice_id];
    if ($status === 'paid') {
        $extra = ', paid_date = CURDATE()';
    }

    $r = db_query("UPDATE project_invoices SET status = ? $extra WHERE id = ?", $params);

    // If paid, also mark stage paid
    if ($r && $status === 'paid') {
        $inv = db_fetch("SELECT stage_id FROM project_invoices WHERE id=?", [$invoice_id]);
        if ($inv && $inv['stage_id']) {
            db_query("UPDATE project_stages SET status='paid' WHERE id=?", [$inv['stage_id']]);
        }
    }

    return $r;
}

/**
 * Get projects for a customer
 */
function project_get_by_customer($customerId) {
    return db_fetch_all("SELECT * FROM projects WHERE customer_id = ? AND status = 'active'", [$customerId]);
}
