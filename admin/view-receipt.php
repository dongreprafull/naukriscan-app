<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_permission('subscriptions.manage'); $pdo = db();
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM payments WHERE id=? LIMIT 1'); $st->execute([$id]); $pay = $st->fetch();
if (!$pay || !$pay['receipt_file_path']) { http_response_code(404); exit('Receipt not found.'); }
$base = realpath(__DIR__ . '/../storage/payment-receipts');
$path = realpath(__DIR__ . '/../' . $pay['receipt_file_path']);
if (!$base || !$path || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) { http_response_code(404); exit('Receipt unavailable.'); }
$finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($path);
header('Content-Type: ' . ($mime ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="receipt-' . (int)$pay['id'] . '"');
readfile($path); exit;
