<?php
/**
 * Post Journal Entry to General Ledger
 * Moves a draft journal entry to posted status and records rows in general_ledger
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Invalid request method.', 'error');
    redirect('journal_entries.php');
    exit;
}

// Verify CSRF
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('Invalid security token.', 'error');
    redirect('journal_entries.php');
    exit;
}

$entry_id = intval($_POST['entry_id'] ?? 0);
if ($entry_id <= 0) {
    set_flash('Invalid journal entry.', 'error');
    redirect('journal_entries.php');
    exit;
}

db_begin_transaction();
try {
    // Fetch the entry
    $entry = db_fetch("SELECT * FROM journal_entries WHERE id = ?", [$entry_id]);
    if (!$entry) {
        throw new Exception('Journal entry not found.');
    }
    if ($entry['status'] === 'posted') {
        throw new Exception('Journal entry is already posted.');
    }

    // Approval gate before posting
    $action = $_POST['action'] ?? 'approve';
    $is_reject = ($action === 'reject');
    
    $approval = finance_process_approval_action($entry, current_user_id(), $is_reject);
    if (!$approval['ok']) {
        throw new Exception($approval['message']);
    }

    if (!empty($approval['fields'])) {
        $set_parts = [];
        $set_params = [];
        foreach ($approval['fields'] as $field => $value) {
            $set_parts[] = "{$field} = ?";
            $set_params[] = $value;
        }
        
        if ($is_reject) {
            $set_parts[] = "status = 'rejected'";
        } elseif (!$approval['approved']) {
            $set_parts[] = "status = 'pending_approval'";
        }
        
        $set_parts[] = "updated_at = NOW()";
        $set_params[] = $entry_id;
        db_query("UPDATE journal_entries SET " . implode(', ', $set_parts) . " WHERE id = ?", $set_params);
        
        if ($is_reject || !$approval['approved']) {
            db_commit();
            set_flash($approval['message'], 'success');
            redirect('view_journal_entry.php?id=' . $entry_id);
            exit;
        }
    }

    // Fetch lines
    $lines = db_fetch_all(
        "SELECT * FROM journal_entry_lines WHERE journal_entry_id = ?",
        [$entry_id]
    );
    if (empty($lines)) {
        throw new Exception('Cannot post a journal entry with no lines.');
    }

    // Check fiscal year lock (only if table exists)
    try {
        $closed_fy = db_fetch(
            "SELECT year_name FROM fiscal_years WHERE status='closed' AND ? BETWEEN start_date AND end_date LIMIT 1",
            [$entry['entry_date']]
        );
        if ($closed_fy) {
            throw new Exception("Cannot post: the entry date falls within closed fiscal year '{$closed_fy['year_name']}'. Re-open the period first.");
        }
    } catch (Exception $fye) {
        // If table doesn't exist yet, skip check
        if (strpos($fye->getMessage(), 'Cannot post') !== false) throw $fye;
    }

    // Validate balance
    $total_debit  = array_sum(array_column($lines, 'debit'));
    $total_credit = array_sum(array_column($lines, 'credit'));
    if (abs($total_debit - $total_credit) > 0.01) {
        throw new Exception('Cannot post an unbalanced entry. Debit and credit totals must match.');
    }

    // Write each line to general_ledger
    foreach ($lines as $line) {
        // Running balance per account (debit increases, credit decreases)
        $prev = db_fetch(
            "SELECT COALESCE(SUM(debit) - SUM(credit), 0) AS bal FROM general_ledger WHERE account_id = ?",
            [$line['account_id']]
        );
        $running_balance = ($prev['bal'] ?? 0) + $line['debit'] - $line['credit'];

        db_insert(
            "INSERT INTO general_ledger 
                (account_id, transaction_date, description, debit, credit, balance, reference_type, reference_id, created_at, cost_center_id, project_id, company_id)
             VALUES (?, ?, ?, ?, ?, ?, 'journal_entry', ?, NOW(), ?, ?, ?)",
            [
                $line['account_id'],
                $entry['entry_date'],
                $entry['description'] ?: $line['description'],
                $line['debit'],
                $line['credit'],
                $running_balance,
                $entry_id,
                $line['cost_center_id'],
                $line['project_id'],
                $line['company_id']
            ]
        );
    }

    // Mark the journal entry as posted
    db_query(
        "UPDATE journal_entries SET status = 'posted', updated_at = NOW() WHERE id = ?",
        [$entry_id]
    );

    db_commit();

    // Write audit log
    try {
        $current_user = db_fetch("SELECT username FROM users WHERE id = ?", [current_user_id()]);
        db_query(
            "INSERT IGNORE INTO finance_audit_log (user_id, username, action, table_name, record_id, details, created_at)
             VALUES (?, ?, 'POST_JOURNAL_ENTRY', 'journal_entries', ?, ?, NOW())",
            [current_user_id(), $current_user['username'] ?? 'unknown', $entry_id,
             "Posted journal entry #{$entry['entry_number']} to General Ledger"]
        );
    } catch (Exception $ae) { /* silently skip if audit table not yet created */ }

    set_flash('Journal entry posted to General Ledger successfully!', 'success');
    redirect('view_journal_entry.php?id=' . $entry_id);

} catch (Exception $e) {
    db_rollback();
    log_error("Error posting journal entry ID {$entry_id}: " . $e->getMessage());
    set_flash('Error posting entry: ' . $e->getMessage(), 'error');
    redirect('view_journal_entry.php?id=' . $entry_id);
}
