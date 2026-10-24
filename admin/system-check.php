<?php
require_once __DIR__.'/../config/app.php';
require_permission('settings.manage');

$checks=[];
$checks[]=['PHP version', PHP_VERSION_ID >= 80200, PHP_VERSION];
$checks[]=['PDO extension', extension_loaded('pdo'), ''];
$checks[]=['PDO MySQL driver', extension_loaded('pdo_mysql'), ''];
$checks[]=['HTTPS', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'), 'Production should use HTTPS'];

try {
    $pdo=db();
    $tables=['users','user_profiles','user_documents','jobs','job_eligibility','job_dates','applications','notifications','notification_queue','subscription_plans','subscriptions','payments','admin_users','admin_roles','permissions','admin_role_permissions','audit_logs','login_attempts','privacy_consents','account_deletion_requests','application_templates','application_template_fields','application_assistance_records','ai_extraction_logs'];
    $missing=[];
    $st=$pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?");
    foreach($tables as $table){ $st->execute([$table]); if(!(int)$st->fetchColumn()) $missing[]=$table; }
    $checks[]=['Database connection', true, 'Connected'];
    $checks[]=['Required tables', !$missing, $missing ? 'Missing: '.implode(', ',$missing) : 'All required tables present'];
} catch(Throwable $e) {
    $checks[]=['Database connection', false, 'Connection failed'];
    $checks[]=['Required tables', false, 'Not checked'];
}

$storageDirs=['../storage/ai','../storage/documents'];
foreach($storageDirs as $dir){
    $path=__DIR__.'/'.$dir;
    $checks[]=['Storage: '.basename($path), is_dir($path) && is_writable($path), is_dir($path) ? (is_writable($path)?'Writable':'Not writable') : 'Missing'];
}

$aiKey=getenv('OPENAI_API_KEY') ?: '';
$checks[]=['AI API key configured', $aiKey !== '', $aiKey === '' ? 'Not configured (AI review will not call provider)' : 'Configured'];
require_once __DIR__.'/../includes/notifications_settings.php';
try { $emailCfg = get_notification_settings($pdo); $emailOk = true; $emailDetail = ($emailCfg['email_provider'] === 'smtp') ? 'SMTP: '.($emailCfg['smtp_host'] ?: 'not set') : 'Hostinger native mail (no setup needed)'; }
catch(Throwable $e) { $emailOk = false; $emailDetail = 'Settings table missing - run sql/v21-email-system.sql'; }
$checks[]=['Email provider configured', $emailOk, $emailDetail];
$waKey=getenv('WHATSAPP_ACCESS_TOKEN') ?: '';
$checks[]=['WhatsApp provider configured', $waKey !== '' && (getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '') !== '', $waKey !== '' ? 'Configured' : 'Not configured'];

$pass=0; foreach($checks as $c) if($c[1]) $pass++;
include __DIR__.'/../includes/header.php';
?>
<div class="container">
  <h1>System Check</h1>
  <p>Production readiness checks. Secrets are never displayed.</p>
  <table><thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead><tbody>
  <?php foreach($checks as $c): ?>
    <tr><td><?=e($c[0])?></td><td><?= $c[1] ? 'PASS' : 'ATTENTION' ?></td><td><?=e($c[2])?></td></tr>
  <?php endforeach; ?>
  </tbody></table>
  <p><strong><?=count($checks)?></strong> checks; <strong><?= $pass ?></strong> currently passing.</p>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
