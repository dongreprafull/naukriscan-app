<?php
require_once 'config/app.php';
require_once 'includes/security.php';
require_once 'includes/notifier.php';
start_secure_session();
require_user();

$pdo = db();
$uid = (int)$_SESSION['user_id'];
$user = $pdo->prepare("SELECT id,email,email_verified FROM users WHERE id=? LIMIT 1");
$user->execute([$uid]); $user = $user->fetch();
if (!$user) redirect('login.php');

$message = null; $error = null;

function issue_verification(PDO $pdo, int $uid, string $email): array {
    $token = bin2hex(random_bytes(32));
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 3600);
    $pdo->prepare("INSERT INTO email_verifications(user_id,token,code,expires_at) VALUES(?,?,?,?)")
        ->execute([$uid, $token, $code, $expires]);
    $link = (defined('APP_URL') && APP_URL ? APP_URL : '') . '/verify-email.php?token=' . $token;
    $html = '<div style="font-family:Arial,sans-serif"><h2>Verify your email</h2>'
        . '<p>Click to verify: <a href="' . e($link) . '">' . e($link) . '</a></p>'
        . '<p>Or enter this code in the app: <strong style="font-size:20px">' . e($code) . '</strong></p>'
        . '<p>This link/code expires in 1 hour.</p></div>';
    return send_raw_email($email, '', 'Verify your NaukriScan email', $html);
}

if (!(int)$user['email_verified']) {
    // Handle a clicked link.
    if (isset($_GET['token'])) {
        $tok = $_GET['token'];
        $st = $pdo->prepare("SELECT * FROM email_verifications WHERE token=? AND user_id=? AND verified_at IS NULL AND expires_at>NOW() LIMIT 1");
        $st->execute([$tok, $uid]); $row = $st->fetch();
        if ($row) {
            $pdo->prepare("UPDATE email_verifications SET verified_at=NOW() WHERE id=?")->execute([$row['id']]);
            $pdo->prepare("UPDATE users SET email_verified=1 WHERE id=?")->execute([$uid]);
            $user['email_verified'] = 1;
            $message = 'Email verified successfully.';
        } else {
            $error = 'This verification link is invalid or has expired.';
        }
    }
    // Handle a submitted code.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code'])) {
        if (!csrf_check($_POST['_csrf'] ?? '')) { $error = 'Invalid request.'; }
        else {
            $code = trim($_POST['code']);
            $st = $pdo->prepare("SELECT * FROM email_verifications WHERE user_id=? AND verified_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1");
            $st->execute([$uid]); $row = $st->fetch();
            if (!$row || (int)$row['attempts'] >= 5) {
                $error = 'No active code. Please resend.';
            } elseif ($row['code'] === $code) {
                $pdo->prepare("UPDATE email_verifications SET verified_at=NOW() WHERE id=?")->execute([$row['id']]);
                $pdo->prepare("UPDATE users SET email_verified=1 WHERE id=?")->execute([$uid]);
                $user['email_verified'] = 1;
                $message = 'Email verified successfully.';
            } else {
                $pdo->prepare("UPDATE email_verifications SET attempts=attempts+1 WHERE id=?")->execute([$row['id']]);
                $error = 'Incorrect code.';
            }
        }
    }
    // Resend (explicit button) or first visit to this page (auto-send once).
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend'])) {
        if (!csrf_check($_POST['_csrf'] ?? '')) { $error = 'Invalid request.'; }
        else {
            $r = issue_verification($pdo, $uid, $user['email']);
            $message = $r['ok'] ? 'Verification email sent to ' . $user['email'] . '.' : null;
            $error = $r['ok'] ? null : ('Could not send email: ' . ($r['error'] ?? 'unknown error'));
        }
    } elseif (!isset($_GET['token']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $has = $pdo->prepare("SELECT 1 FROM email_verifications WHERE user_id=? AND verified_at IS NULL AND expires_at>NOW() LIMIT 1");
        $has->execute([$uid]);
        if (!$has->fetchColumn()) { issue_verification($pdo, $uid, $user['email']); $message = 'Verification email sent to ' . $user['email'] . '.'; }
    }
}

$page_title = 'Verify Email'; $asset_prefix = ''; $home_link = 'index.php';
require 'includes/header.php';
?>
<div class="form-wrap"><div class="form-card">
<h1>Verify Your Email</h1>
<?php if ((int)$user['email_verified']): ?>
    <div class="alert success">Your email is verified. <a href="user/dashboard.php">Go to dashboard</a>.</div>
<?php else: ?>
    <?php if ($message): ?><div class="alert success"><?=e($message)?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
    <p class="muted">We sent a link and a 6-digit code to <strong><?=e($user['email'])?></strong>. Click the link, or enter the code below.</p>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
        <label>6-digit code<input name="code" maxlength="6" pattern="[0-9]{6}"></label>
        <button class="btn btn-primary full">Verify</button>
    </form>
    <form method="post" class="center" style="margin-top:12px">
        <input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
        <button type="submit" name="resend" value="1" class="btn btn-link">Resend email</button>
    </form>
    <p class="center"><a href="user/dashboard.php">Skip for now</a></p>
<?php endif; ?>
</div></div>
<?php require 'includes/footer.php'; ?>
