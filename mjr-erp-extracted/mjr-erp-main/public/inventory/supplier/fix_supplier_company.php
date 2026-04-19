<?php
/**
 * Supplier Company Assignment Fix Tool
 * 
 * STEP 1: Upload this file to the remote server
 * STEP 2: Visit it in a browser as admin
 * STEP 3: Assign each supplier to the correct company
 * STEP 4: DELETE this file after completing
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

// Only admins can access this tool
if (!is_super_admin() && !has_role('company_admin') && !has_role('admin')) {
    die('<h2 style="color:red;font-family:sans-serif;">Access denied. Admin only.</h2>');
}

$message = '';
$error   = '';

// ─── STEP 1: Ensure company_id column exists ────────────────────────────────
$column_exists = false;
try {
    $col = db_fetch("SHOW COLUMNS FROM `suppliers` LIKE 'company_id'");
    $column_exists = !empty($col);
    if (!$column_exists) {
        db_query("ALTER TABLE `suppliers` ADD COLUMN `company_id` INT NULL DEFAULT NULL");
        $column_exists = true;
        $message .= "<p style='color:lime;'>✅ Added <code>company_id</code> column to suppliers table.</p>";
    }
} catch (Throwable $e) {
    $error = "❌ Could not add company_id column: " . $e->getMessage();
}

// ─── STEP 2: Handle bulk assignment POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign']) && $column_exists) {
    $updated = 0;
    foreach (($_POST['supplier_company'] ?? []) as $sup_id => $cid) {
        $cid = (int) $cid;
        if ($cid > 0) {
            db_query("UPDATE suppliers SET company_id = ? WHERE id = ?", [$cid, (int) $sup_id]);
            $updated++;
        }
    }
    $message .= "<p style='color:lime;padding:10px;background:#1a3a1a;border-radius:6px;margin:10px 0;'>
        ✅ Updated <strong>$updated</strong> suppliers. Verify the supplier list per company, then delete this file.
    </p>";
}

// ─── Load data ───────────────────────────────────────────────────────────────
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");
$suppliers = db_fetch_all("
    SELECT s.id, s.name, s.supplier_code, s.company_id,
           c.name AS company_name
    FROM suppliers s
    LEFT JOIN companies c ON c.id = s.company_id
    ORDER BY s.company_id IS NULL DESC, c.name, s.name
");

// ─── Diagnostic: Show how many suppliers per company ─────────────────────────
$company_counts = [];
foreach ($suppliers as $s) {
    $key = $s['company_id'] ?? 'NULL';
    $company_counts[$key] = ($company_counts[$key] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Fix Supplier Company — MJR ERP</title>
<style>
    * { box-sizing: border-box; }
    body { background: #0d1117; color: #e6edf3; font-family: 'Segoe UI', Arial, sans-serif; padding: 24px; margin: 0; }
    h1 { color: #f0883e; margin-bottom: 4px; }
    h3 { color: #58a6ff; }
    .subtitle { color: #8b949e; margin-bottom: 20px; }
    .warning { background: #3d1f00; border: 1px solid #f0883e; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; }
    .info    { background: #0d2137; border: 1px solid #58a6ff; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; }
    .success { background: #0d2117; border: 1px solid #3fb950; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; }
    .error   { background: #2a0d0d; border: 1px solid #f85149; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; }
    th, td { padding: 10px 14px; border: 1px solid #30363d; text-align: left; vertical-align: middle; }
    th { background: #161b22; color: #58a6ff; font-weight: 600; }
    tr:nth-child(even) td { background: #11161d; }
    tr:nth-child(odd)  td { background: #0d1117; }
    tr.unassigned td { background: #1f1a00 !important; }
    select { background: #1f2937; color: #e6edf3; border: 1px solid #374151; padding: 6px 10px; border-radius: 4px; width: 100%; }
    .btn { background: #238636; color: white; padding: 12px 28px; border: none; border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: 600; margin-top: 20px; }
    .btn:hover { background: #2ea043; }
    .badge-null { background: #b91c1c; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
    .badge-ok   { background: #15803d; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; }
    .stat-grid  { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
    .stat-card  { background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 14px 20px; min-width: 180px; }
    .stat-card .num { font-size: 28px; font-weight: 700; color: #f0883e; }
    .stat-card .lbl { font-size: 12px; color: #8b949e; text-transform: uppercase; }
</style>
</head>
<body>

<h1>🔧 Supplier Company Fix Tool</h1>
<p class="subtitle">Assign each supplier to the correct company to enable company-level filtering.</p>

<?php if ($error): ?>
<div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($message): ?>
<div class="success"><?= $message ?></div>
<?php endif; ?>

<div class="warning">
    ⚠️ <strong>One-time migration tool.</strong> After saving, verify the Suppliers page filters correctly per company, then <strong>delete this file from the server.</strong>
</div>

<!-- Diagnostics -->
<div class="info">
    <h3>📊 Current State</h3>
    <p>Total suppliers: <strong><?= count($suppliers) ?></strong> | Total companies: <strong><?= count($companies) ?></strong></p>
    <p>Suppliers with <strong>no company assigned</strong>: 
        <span class="badge-null"><?= count(array_filter($suppliers, fn($s) => !$s['company_id'])) ?></span>
        (these appear in ALL companies — must be assigned)
    </p>
</div>

<!-- Stat breakdown per company -->
<div class="stat-grid">
    <?php foreach ($companies as $c): ?>
    <div class="stat-card">
        <div class="num"><?= $company_counts[$c['id']] ?? 0 ?></div>
        <div class="lbl"><?= htmlspecialchars($c['name']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (isset($company_counts['NULL'])): ?>
    <div class="stat-card">
        <div class="num" style="color:#f85149"><?= $company_counts['NULL'] ?></div>
        <div class="lbl" style="color:#f85149">⚠️ Unassigned</div>
    </div>
    <?php endif; ?>
</div>

<?php if (!$column_exists): ?>
<div class="error">❌ The <code>company_id</code> column could not be added to the suppliers table. Check database permissions.</div>
<?php else: ?>

<form method="POST">
    <input type="hidden" name="assign" value="1">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Supplier Code</th>
                <th>Supplier Name</th>
                <th>Current Company</th>
                <th>Assign to Company ★</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($suppliers as $i => $s):
                $unassigned = empty($s['company_id']);
            ?>
            <tr class="<?= $unassigned ? 'unassigned' : '' ?>">
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($s['supplier_code']) ?></td>
                <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                <td>
                    <?php if ($unassigned): ?>
                        <span class="badge-null">⚠️ Unassigned</span>
                    <?php else: ?>
                        <span class="badge-ok">✅ <?= htmlspecialchars($s['company_name'] ?? '') ?></span>
                        <small style="color:#8b949e"> (ID:<?= $s['company_id'] ?>)</small>
                    <?php endif; ?>
                </td>
                <td>
                    <select name="supplier_company[<?= $s['id'] ?>]">
                        <option value="">-- keep current --</option>
                        <?php foreach ($companies as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($s['company_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?> (ID: <?= $c['id'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit" class="btn">💾 Save Company Assignments</button>
</form>

<?php endif; ?>

<div style="margin-top:30px;padding:14px;background:#161b22;border-radius:8px;color:#8b949e;font-size:13px;">
    📝 <strong>Instructions:</strong><br>
    1. Use the dropdown in the <em>"Assign to Company"</em> column to pick the correct company for each supplier.<br>
    2. Click <strong>Save Company Assignments</strong>.<br>
    3. Go to Inventory → Suppliers and switch between companies — each company should now show only its own suppliers.<br>
    4. <strong>Delete this file</strong> from your server after you're done.
</div>

</body>
</html>
