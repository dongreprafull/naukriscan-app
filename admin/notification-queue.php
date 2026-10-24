<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_permission('notifications.manage'); $pdo=db();
$rows=$pdo->query("SELECT q.*,n.title,n.user_id,u.email,u.mobile FROM notification_queue q JOIN notifications n ON n.id=q.notification_id JOIN users u ON u.id=n.user_id ORDER BY q.queued_at DESC LIMIT 100")->fetchAll();
$queued=(int)$pdo->query("SELECT COUNT(*) FROM notification_queue WHERE status='queued'")->fetchColumn();
$sent=(int)$pdo->query("SELECT COUNT(*) FROM notification_queue WHERE status='sent'")->fetchColumn();
$failed=(int)$pdo->query("SELECT COUNT(*) FROM notification_queue WHERE status='failed'")->fetchColumn();
$page_title='Notification Queue';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">DELIVERY</span><h1>Notification Queue</h1><p class="muted">Email and WhatsApp jobs wait here until the server worker delivers them.</p></div></div>
<div class="stats"><div><b><?=e($queued)?></b><span>Queued</span></div><div><b><?=e($sent)?></b><span>Sent</span></div><div><b><?=e($failed)?></b><span>Failed</span></div></div>
<div class="card"><h2>Provider setup</h2><p>Email sends via Hostinger's own mail relay by default — no setup needed. To switch to another SMTP provider, use <a href="email-settings.php">Email Settings</a>.</p><p class="muted">WhatsApp delivery is coming in a future update.</p><p class="muted">Run <code>php cron/process-notifications.php</code> from a server cron to process the queue.</p></div>
<div class="card"><h2>Recent queue items</h2><div class="table-wrap"><table><tr><th>Channel</th><th>Candidate</th><th>Notification</th><th>Status</th><th>Attempts</th><th>Queued</th></tr><?php foreach($rows as $r):?><tr><td><?=e($r['channel'])?></td><td><?=e($r['email']?:$r['mobile'])?></td><td><?=e($r['title'])?></td><td><?=e($r['status'])?><br><small><?=e($r['last_error']?:'')?></small></td><td><?=e($r['attempts'])?></td><td><?=e($r['queued_at'])?></td></tr><?php endforeach;?></table></div></div>
<?php require '../includes/footer.php';?>
