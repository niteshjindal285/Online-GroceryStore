<?php
/**
 * KYC Document Upload Handler
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

if (!is_post()) { redirect('../debtors/index.php'); }
if (!verify_csrf_token(post('csrf_token'))) {
    set_flash('Invalid CSRF token.', 'error');
    redirect('../debtors/index.php');
}

$customer_id = intval(post('customer_id'));
$doc_type    = trim(post('doc_type', 'Other'));
$user_id     = $_SESSION['user_id'];

if (!$customer_id) { set_flash('Invalid customer.', 'error'); redirect('../debtors/index.php'); }

if (empty($_FILES['document']['name'])) {
    set_flash('No file selected.', 'error');
    redirect("view_debtor.php?id=$customer_id");
}

$file    = $_FILES['document'];
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    set_flash('File too large. Max 5MB.', 'error');
    redirect("view_debtor.php?id=$customer_id");
}

$allowed = ['pdf','jpg','jpeg','png','doc','docx'];
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    set_flash('File type not allowed.', 'error');
    redirect("view_debtor.php?id=$customer_id");
}

$uploadDir = __DIR__ . '/../../../../uploads/debtors/' . $customer_id . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$safeName  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
$fileName  = date('Ymd_His') . '_' . $safeName . '.' . $ext;
$filePath  = $uploadDir . $fileName;
$dbPath    = 'uploads/debtors/' . $customer_id . '/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    set_flash('Upload failed. Please try again.', 'error');
    redirect("view_debtor.php?id=$customer_id");
}

db_insert("INSERT INTO debtor_documents (customer_id, file_name, file_path, doc_type, uploaded_by)
           VALUES (?,?,?,?,?)",
    [$customer_id, $file['name'], $dbPath, $doc_type, $user_id]);

set_flash('Document uploaded successfully.', 'success');
redirect("view_debtor.php?id=$customer_id");
