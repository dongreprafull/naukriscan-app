<?php require_once 'config/app.php'; require_once 'includes/security.php'; start_secure_session(); $errors=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_check($_POST['_csrf']??'')) $errors[]='Invalid request. Please try again.';
 $name=trim($_POST['name']??''); $email=trim($_POST['email']??''); $mobile=trim($_POST['mobile']??''); $pass=$_POST['password']??''; $confirm=$_POST['confirm_password']??'';
 if($name==='') $errors[]='Name is required.'; if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors[]='Valid email is required.';
 if(!preg_match('/^[0-9+\-\s]{8,20}$/',$mobile)) $errors[]='Valid mobile number is required.'; if(strlen($pass)<8) $errors[]='Password must be at least 8 characters.';
 if($pass!==$confirm) $errors[]='Passwords do not match.'; if(empty($_POST['consent'])) $errors[]='Please accept the Privacy Policy and Terms & Conditions.';
 if(!$errors){ try{$pdo=db(); $st=$pdo->prepare("SELECT id FROM users WHERE email=? OR mobile=? LIMIT 1");$st->execute([$email,$mobile]); if($st->fetch()) $errors[]='Email or mobile already registered.'; else{$pdo->beginTransaction();$st=$pdo->prepare("INSERT INTO users(user_uid,mobile,email,password_hash,mobile_verified,email_verified,account_status) VALUES(?,?,?,?,0,0,'active')");$st->execute(['USR-'.strtoupper(bin2hex(random_bytes(4))),$mobile,$email,password_hash($pass,PASSWORD_DEFAULT)]);$uid=$pdo->lastInsertId();$st=$pdo->prepare("INSERT INTO user_profiles(user_id,first_name) VALUES(?,?)");$st->execute([$uid,$name]);$pdo->prepare("INSERT INTO privacy_consents(user_id,consent_type,version,consented_at,ip_address) VALUES(?,?,?,?,?)")->execute([$uid,'terms_privacy','v1',date('Y-m-d H:i:s'),client_ip()]);$pdo->commit();rotate_session_on_login();$_SESSION['user_id']=$uid;redirect('user/dashboard.php');}}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$errors[]='Registration could not be completed.';}}}
$page_title='Register'; $asset_prefix=''; $home_link='index.php'; require 'includes/header.php'; ?>
<div class="form-wrap"><div class="form-card"><h1>Create Candidate Profile</h1><p class="muted">Start with your basic account. You can complete your profile after login.</p>
<?php foreach($errors as $x): ?><div class="alert error"><?=e($x)?></div><?php endforeach; ?>
<form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<label>Full Name<input name="name" value="<?=old('name')?>" required></label>
<label>Email<input type="email" name="email" value="<?=old('email')?>" required></label>
<label>Mobile<input name="mobile" value="<?=old('mobile')?>" required></label>
<label>Password<input type="password" name="password" required></label>
<label>Confirm Password<input type="password" name="confirm_password" required></label>
<label><input type="checkbox" name="consent" value="1" required> I agree to the <a href="privacy.php">Privacy Policy</a> and <a href="terms.php">Terms & Conditions</a>.</label>
<button class="btn btn-primary full">Create Account</button></form><p class="center">Already registered? <a href="login.php">Login</a></p></div></div>
<?php require 'includes/footer.php'; ?>