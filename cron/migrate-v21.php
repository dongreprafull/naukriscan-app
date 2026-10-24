<?php
// One-time migration runner for V21 (email settings + verification tables).
// Protected by a secret; self-neuters after a successful run.
require_once __DIR__ . '/../config/app.php';
$SECRET = '9db8b6ef6f3e0225c2d02987d79ab110240d18eabe4bf6dd';
if (($_GET['key'] ?? '') !== $SECRET) { http_response_code(403); exit('Forbidden'); }

header('Content-Type: application/json');
try {
    $pdo = db();
    $sql = file_get_contents(__DIR__ . '/../sql/v21-email-system.sql');
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $ran = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '' || strpos($stmt, '--') === 0) continue;
        $pdo->exec($stmt);
        $ran++;
    }
    echo json_encode(['ok' => true, 'statements_run' => $ran]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
