<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_permission('settings.manage'); require_once '../includes/notifications_settings.php';
$pdo = db(); $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $provider = ($_POST['email_provider'] ?? 'hostinger') === 'smtp' ? 'smtp' : 'hostinger';
        $fromEmail = trim($_POST['from_email'] ?? '');
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) { $error = 'A valid "From" email is required.'; }
        else {
            $fields = [
                'email_provider' => $provider,
                'from_email' => $fromEmail,
                'from_name' => trim($_POST['from_name'] ?? 'NaukriScan'),
                'smtp_host' => trim($_POST['smtp_host'] ?? ''),
                'smtp_port' => (int)($_POST['smtp_port'] ?? 587),
                'smtp_username' => trim($_POST['smtp_username'] ?? ''),
                'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? 'tls', ['tls','ssl','none'], true) ? $_POST['smtp_encryption'] : 'tls',
            ];
            // Only overwrite the stored password if a new one was typed (field left blank keeps the existing one).
            if (trim($_POST['smtp_password'] ?? '') !== '') { $fields['smtp_password'] = trim($_POST['smtp_password']); }
            try {
                update_notification_settings($pdo, $fields);
                flash('success', 'Email settings saved.');
            } catch (Throwable $e) {
                flash('error', 'Could not save — the email-settings table is not set up yet. Run sql/v21-email-system.sql first.');
            }
            redirect('email-settings.php');
        }
    }
}
$tableMissing = false;
try {
    $cfg = $pdo->query("SELECT * FROM app_notification_settings WHERE id=1 LIMIT 1")->fetch();
} catch (Throwable $e) {
    $tableMissing = true; $cfg = false;
}
if (!$cfg) { $cfg = ['email_provider'=>'hostinger','from_email'=>'noreply@naukriscan.in','from_name'=>'NaukriScan','smtp_host'=>'','smtp_port'=>587,'smtp_username'=>'','smtp_encryption'=>'tls']; }
$page_title='Email Settings'; $asset_prefix='../'; $home_link='../index.php'; require '../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">ADMIN</span><h1>Email Settings</h1><p class="muted">Controls how NaukriScan sends job-match alerts and verification emails.</p></div></div>
<?php if($tableMissing):?><div class="alert error">The email-settings table isn't set up yet. Run <code>sql/v21-email-system.sql</code> in phpMyAdmin, then reload this page. Showing defaults below (not yet saved).</div><?php endif;?>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="card">
<form method="post">
<input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<label>Provider
  <select name="email_provider" id="provider-select">
    <option value="hostinger" <?=$cfg['email_provider']==='hostinger'?'selected':''?>>Hostinger (built-in, no setup needed)</option>
    <option value="smtp" <?=$cfg['email_provider']==='smtp'?'selected':''?>>Custom SMTP (Brevo, SendGrid, Titan, etc.)</option>
  </select>
</label>
<div class="grid-2">
  <label>From Email<input type="email" name="from_email" value="<?=e($cfg['from_email'])?>" required></label>
  <label>From Name<input name="from_name" value="<?=e($cfg['from_name'])?>"></label>
</div>
<fieldset id="smtp-fields">
  <legend>SMTP details (only used when Provider = Custom SMTP)</legend>
  <div class="grid-2">
    <label>SMTP Host<input name="smtp_host" value="<?=e($cfg['smtp_host']??'')?>" placeholder="smtp.brevo.com"></label>
    <label>Port<input type="number" name="smtp_port" value="<?=e($cfg['smtp_port']??587)?>"></label>
    <label>Username<input name="smtp_username" value="<?=e($cfg['smtp_username']??'')?>"></label>
    <label>Password<input type="password" name="smtp_password" placeholder="Leave blank to keep current password"></label>
    <label>Encryption
      <select name="smtp_encryption">
        <option value="tls" <?=($cfg['smtp_encryption']??'tls')==='tls'?'selected':''?>>STARTTLS</option>
        <option value="ssl" <?=($cfg['smtp_encryption']??'')==='ssl'?'selected':''?>>SSL</option>
        <option value="none" <?=($cfg['smtp_encryption']??'')==='none'?'selected':''?>>None</option>
      </select>
    </label>
  </div>
</fieldset>
<button class="btn btn-primary">Save Settings</button>
</form>
</div>
<?php require '../includes/footer.php';?>
