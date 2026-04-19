<?php
/**
 * Update Production Order Progress
 * Saves the progress_percent slider value for an in-progress production order
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('production_orders.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('Invalid security token.', 'error');
    redirect('production_orders.php');
    exit;
}

$wo_id    = intval($_POST['wo_id'] ?? 0);
$progress = intval($_POST['progress_percent'] ?? 0);
$progress = max(0, min(100, $progress)); // clamp 0-100

if ($wo_id <= 0) {
    set_flash('Invalid production order.', 'error');
    redirect('production_orders.php');
    exit;
}

try {
    // Auto-add the column if it doesn't exist yet (safe to call repeatedly)
    $col_check = db_fetch(
        "SELECT COUNT(*) as cnt FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_orders' AND COLUMN_NAME = 'progress_percent'"
    );
    if (intval($col_check['cnt'] ?? 0) === 0) {
        db_query("ALTER TABLE work_orders ADD COLUMN progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status");
    }

    $wo = db_fetch("SELECT id, status, wo_number FROM work_orders WHERE id = ?", [$wo_id]);
    if (!$wo) throw new Exception('Production order not found.');
    if ($wo['status'] !== 'in_progress') throw new Exception('Can only update progress of an in-progress production order.');

    db_query("UPDATE work_orders SET progress_percent = ? WHERE id = ?", [$progress, $wo_id]);
    set_flash("Progress updated to {$progress}% for Production Order {$wo['wo_number']}.", 'success');

} catch (Exception $e) {
    log_error("Error updating Production Order progress: " . $e->getMessage());
    set_flash('Error: ' . $e->getMessage(), 'error');
}

redirect("view_production_order.php?id={$wo_id}");
