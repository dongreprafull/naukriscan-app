<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_permission('settings.manage'); require_once '../includes/settings.php';
$pdo = db(); $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $upi = trim($_POST['upi_id'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        if ($upi === '') { $error = 'UPI ID is required.'; }
        else {
            $qrPath = get_setting($pdo, 'payment_qr_image');
            if (!empty($_FILES['qr_image']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
                $f = $_FILES['qr_image'];
                if ($f['size'] <= 0 || $f['size'] > 5 * 1024 * 1024) { $error = 'QR image must be under 5 MB.'; }
                else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($f['tmp_name']);
                    $valid = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
                    if (!isset($valid[$mime]) || @getimagesize($f['tmp_name']) === false) { $error = 'QR image must be a JPG or PNG.'; }
                    else {
                        $dir = __DIR__ . '/../storage/payment-qr';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        if (!is_file($dir . '/.htaccess')) {
                            file_put_contents($dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php[0-9]*|phtml|phar|cgi|pl|py|sh|asp|aspx)$\">\n  Require all denied\n</FilesMatch>\n");
                        }
                        $name = 'qr-' . bin2hex(random_bytes(8)) . '.' . $valid[$mime];
                        $dest = $dir . '/' . $name;
                        require_once '../includes/uploads.php';
                        if (compress_and_store_image($f['tmp_name'], $mime, $dest, 800, 85) && is_file($dest)) {
                            $qrPath = 'storage/payment-qr/' . $name;
                        } else { $error = 'Could not save the QR image.'; }
                    }
                }
            }
            if (!$error) {
                set_setting($pdo, 'payment_upi_id', $upi);
                set_setting($pdo, 'payment_qr_image', $qrPath);
                set_setting($pdo, 'payment_instructions', $instructions);
                flash('success', 'Payment settings saved.');
                redirect('payment-settings.php');
            }
        }
    }
}

$cfg = get_settings($pdo, ['payment_upi_id', 'payment_qr_image', 'payment_instructions']);
$page_title = 'Payment Settings'; $asset_prefix = '../'; $home_link = '../index.php'; require '../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">ADMIN</span><h1>Payment Settings</h1><p class="muted">The QR code and UPI ID shown to candidates on the Subscription page for manual payment.</p></div></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="grid-2">
<div class="card">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<label>UPI ID<input name="upi_id" value="<?= e($cfg['payment_upi_id']) ?>" placeholder="yourname@upi" required></label>
<label>Payment Instructions (shown to candidates)<textarea name="instructions" rows="3"><?= e($cfg['payment_instructions']) ?></textarea></label>
<label>QR Code Image (JPG/PNG, max 5 MB)<input type="file" name="qr_image" accept=".jpg,.jpeg,.png"></label>
<div class="form-actions"><button class="btn btn-primary">Save</button></div>
</form>
</div>
<div class="card">
<h2>Current QR Code</h2>
<?php if ($cfg['payment_qr_image']): ?><img class="payment-qr" src="../<?= e($cfg['payment_qr_image']) ?>" alt="Payment QR"><?php else: ?><p class="muted">No QR code uploaded yet.</p><?php endif; ?>
<p class="muted" style="margin-top:12px">UPI ID: <strong><?= e($cfg['payment_upi_id'] ?: 'Not set') ?></strong></p>
</div>
</div>
<div class="card"><h2>Verify incoming payments</h2><p class="muted">Candidates who upload a transaction ID/receipt for a paid plan appear on the <a href="payments.php">Payments</a> page for verification.</p><a class="btn btn-light" href="payments.php">Open Payments →</a></div>
<?php require '../includes/footer.php'; ?>
