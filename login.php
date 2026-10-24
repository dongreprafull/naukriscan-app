<?php
require_once 'config/app.php';
require_once 'includes/security.php';
start_secure_session();
$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_check($_POST['_csrf']??'')) $error='Invalid request.';
    else {
        $identity=trim($_POST['identity']??''); $pass=$_POST['password']??'';
        if(!login_rate_allowed('candidate',$identity)) $error='Too many login attempts. Please try again later.';
        else {
            try {
                $pdo=db(); $st=$pdo->prepare("SELECT * FROM users WHERE email=? OR mobile=? LIMIT 1");
                $st->execute([$identity,$identity]); $u=$st->fetch();
                if($u && $u['account_status']==='active' && password_verify($pass,$u['password_hash'])){
                    record_login_attempt('candidate',$identity,true); rotate_session_on_login();
                    $_SESSION['user_id']=$u['id'];
                    $pdo->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$u['id']]);
                    redirect('user/dashboard.php');
                }
                record_login_attempt('candidate',$identity,false); $error='Invalid login details or inactive account.';
            } catch(Throwable $e){ $error='Database connection/configuration issue.'; }
        }
    }
}
$page_title='Candidate Login'; $asset_prefix=''; $home_link='index.php'; require 'includes/header.php'; ?>
<div class="form-wrap"><div class="form-card"><h1>Candidate Login</h1><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<label>Email or Mobile<input name="identity" required></label><label>Password<input type="password" name="password" required></label>
<button class="btn btn-primary full">Login</button></form><div class="split"><a href="register.php">Create account</a><a href="forgot-password.php">Forgot password?</a></div>
<hr><p class="center"><a href="admin/index.php">Admin Login</a></p></div></div><?php require 'includes/footer.php'; ?>
