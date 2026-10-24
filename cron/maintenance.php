<?php
// Run from Hostinger cron, preferably hourly: php /home/USER/domains/DOMAIN/public_html/cron/maintenance.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/subscription.php';
$pdo = db();
$expiredBefore = 0;
$pdo->beginTransaction();
try {
    $before = (int)$pdo->query("SELECT COUNT(*) FROM subscriptions WHERE status='active' AND end_date<CURDATE()")->fetchColumn();
    expire_subscriptions($pdo);
    $expiredBefore = $before;
    // Recover jobs left in notification processing state by an interrupted worker.
    $pdo->exec("UPDATE notification_queue SET status='queued' WHERE status='processing' AND processed_at IS NULL AND queued_at < (NOW() - INTERVAL 30 MINUTE) AND attempts < 3");
    // Remove stale login-attempt records; this table is operational security data, not an audit archive.
    $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)");
    $pdo->commit();
    echo json_encode(['ok'=>true,'expired_subscriptions'=>$expiredBefore,'message'=>'Maintenance completed']).PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Maintenance failed: ".$e->getMessage().PHP_EOL);
    exit(1);
}
