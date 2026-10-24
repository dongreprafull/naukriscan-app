<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_user(); $pdo = db();
require_once '../includes/subscription.php'; require_once '../includes/settings.php'; require_once '../includes/plan-highlight.php'; require_once '../includes/uploads.php';
$uid = (int)$_SESSION['user_id']; expire_subscriptions($pdo); $sub = current_subscription($pdo, $uid); $message = null; $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!csrf_check($_POST['_csrf'] ?? '')) { $error = 'Invalid request.'; }
    elseif ($action === 'start_trial') {
        if ($sub) { $error = 'You already have an active subscription.'; }
        else { try { $sub = ensure_trial($pdo, $uid); $message = $sub ? 'Free trial activated.' : 'Free trial is currently unavailable.'; } catch (Throwable $e) { $error = 'Unable to activate trial right now.'; } }
    } elseif ($action === 'submit_payment') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $txn = trim($_POST['transaction_id'] ?? '');
        $ps = $pdo->prepare("SELECT * FROM subscription_plans WHERE id=? AND status='active' AND is_trial=0"); $ps->execute([$planId]); $plan = $ps->fetch();
        if (!$plan) { $error = 'Please choose a valid paid plan.'; }
        elseif ($txn === '') { $error = 'Please enter your UPI transaction ID.'; }
        elseif (empty($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) { $error = 'Please upload a payment screenshot or receipt.'; }
        else {
            $pend = $pdo->prepare("SELECT id FROM payments WHERE user_id=? AND plan_id=? AND payment_status='pending' LIMIT 1");
            $pend->execute([$uid, $planId]);
            if ($pend->fetch()) { $error = 'You already have a submission for this plan pending review.'; }
            else {
                $f = $_FILES['receipt']; $max = 10 * 1024 * 1024;
                if ($f['size'] <= 0 || $f['size'] > $max) { $error = 'Receipt file must be under 10 MB.'; }
                else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE); $mime = $finfo->file($f['tmp_name']);
                    $valid = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
                    if (!isset($valid[$mime])) { $error = 'Only JPG, PNG or PDF receipts are allowed.'; }
                    else {
                        try {
                            $dir = __DIR__ . '/../storage/payment-receipts/' . $uid;
                            if (!is_dir($dir)) mkdir($dir, 0750, true);
                            if (!is_file($dir . '/.htaccess')) { file_put_contents($dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php[0-9]*|phtml|phar|cgi|pl|py|sh|asp|aspx)$\">\n  Require all denied\n</FilesMatch>\n"); }
                            $name = bin2hex(random_bytes(16)) . '.' . $valid[$mime];
                            $dest = $dir . '/' . $name;
                            $saved = ($mime === 'application/pdf') ? move_uploaded_file($f['tmp_name'], $dest) : compress_and_store_image($f['tmp_name'], $mime, $dest);
                            if (!$saved || !is_file($dest)) throw new Exception();
                            $relative = 'storage/payment-receipts/' . $uid . '/' . $name;
                            $pdo->prepare("INSERT INTO payments(user_id,plan_id,transaction_id,amount,currency,payment_gateway,payment_status,receipt_file_path,created_at) VALUES(?,?,?,?,'INR','manual_upi','pending',?,NOW())")
                                ->execute([$uid, $planId, $txn, $plan['price'], $relative]);
                            $message = 'Payment submitted — your ' . $plan['plan_name'] . ' plan will be activated after verification (usually within a few hours).';
                        } catch (Throwable $e) { $error = 'Could not submit your payment. Please try again.'; }
                    }
                }
            }
        }
    }
}

$plans = $pdo->query("SELECT * FROM subscription_plans WHERE status='active' ORDER BY sort_order,id")->fetchAll();
$pay = get_settings($pdo, ['payment_upi_id', 'payment_qr_image', 'payment_instructions']);
$pendingPayments = $pdo->prepare("SELECT p.*, pl.plan_name FROM payments p LEFT JOIN subscription_plans pl ON pl.id=p.plan_id WHERE p.user_id=? AND p.payment_status='pending' ORDER BY p.created_at DESC");
$pendingPayments->execute([$uid]); $pendingRows = $pendingPayments->fetchAll();
$page_title = 'Subscription'; $asset_prefix = '../'; $home_link = '../index.php'; require '../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">CANDIDATE PLAN</span><h1>Subscription</h1><p class="muted">Job-match alerts are free for life. Upgrade for unlimited auto-fill. Government application fees are never collected here.</p></div></div>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="card"><h2>Current access</h2><?php if ($sub): ?><p><strong><?= e($sub['plan_name']) ?></strong> · <?= e($sub['subscription_type']) ?></p><p>Valid: <?= e($sub['start_date']) ?> to <?= e($sub['end_date']) ?></p><?php else: ?><p>No active subscription.</p><?php endif; ?></div>

<?php if ($pendingRows): ?>
<div class="card" style="background:#fffaf0;border-color:#ffe3a3">
<h2>⏳ Pending verification</h2>
<?php foreach ($pendingRows as $pr): ?>
<p><strong><?= e($pr['plan_name'] ?: 'Plan') ?></strong> · ₹<?= number_format((float)$pr['amount'], 2) ?> · Txn: <?= e($pr['transaction_id']) ?> · submitted <?= e($pr['created_at']) ?></p>
<?php endforeach; ?>
<p class="muted">We'll activate your plan once our team verifies the payment.</p>
</div>
<?php endif; ?>

<?php render_plan_highlight(['compact' => true]); ?>

<div class="cards">
<?php foreach ($plans as $plan): ?>
<div class="card">
<h2><?= e($plan['plan_name']) ?></h2>
<p><strong>₹<?= number_format((float)$plan['price'], 2) ?></strong> · <?= e($plan['duration_days']) ?> days</p>
<p><?= nl2br(e(str_replace(';', "\n", $plan['features'] ?? ''))) ?></p>
<?php if ((int)$plan['is_trial'] === 1): ?>
<form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="start_trial"><button class="btn btn-primary" <?= $sub ? 'disabled' : '' ?>>Start Free Trial</button></form>
<?php elseif ((float)$plan['price'] > 0): ?>
<details>
<summary class="btn btn-primary" style="cursor:pointer;list-style:none">Pay via QR / UPI</summary>
<div style="margin-top:14px">
<?php if ($pay['payment_qr_image']): ?><img class="payment-qr" src="../<?= e($pay['payment_qr_image']) ?>" alt="Payment QR"><?php endif; ?>
<p style="margin:10px 0 2px">UPI ID: <strong><?= e($pay['payment_upi_id'] ?: 'Not configured yet — please contact support.') ?></strong></p>
<?php if ($pay['payment_instructions']): ?><p class="muted"><?= nl2br(e($pay['payment_instructions'])) ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:10px">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<input type="hidden" name="action" value="submit_payment">
<input type="hidden" name="plan_id" value="<?= e($plan['id']) ?>">
<label class="span-2">UPI Transaction ID<input name="transaction_id" required placeholder="e.g. 123456789012"></label>
<label class="span-2">Payment Screenshot / Receipt (JPG, PNG or PDF, max 10 MB)<input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required></label>
<div class="form-actions"><button class="btn btn-primary btn-small">Submit for Verification</button></div>
</form>
</div>
</details>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php require '../includes/footer.php'; ?>
