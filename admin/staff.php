<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_permission('staff.manage'); $pdo=db(); $error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_check($_POST['_csrf']??'')) $error='Invalid request.';
 else try{
  $name=trim($_POST['name']??''); $email=strtolower(trim($_POST['email']??'')); $mobile=trim($_POST['mobile']??''); $role=trim($_POST['role']??''); $password=$_POST['password']??'';
  $roles=$pdo->query("SELECT role_name FROM admin_roles ORDER BY role_name")->fetchAll(PDO::FETCH_COLUMN);
  if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<10||!in_array($role,$roles,true)) throw new Exception('validation');
  $uid='ADM-'.strtoupper(bin2hex(random_bytes(4)));
  $pdo->prepare("INSERT INTO admin_users(admin_uid,name,email,mobile,password_hash,role,status) VALUES(?,?,?,?,?,?, 'active')")->execute([$uid,$name,$email,$mobile,password_hash($password,PASSWORD_DEFAULT),$role]);
  audit_admin('CREATE','admin_users',(int)$pdo->lastInsertId(),null,$role); flash('success','Staff account created.'); redirect('staff.php');
 }catch(Throwable $e){$error=$e->getCode()===23000?'Email already exists.':'Could not create staff account.';}
}
$staff=$pdo->query("SELECT id,admin_uid,name,email,mobile,role,status,last_login,created_at FROM admin_users ORDER BY created_at DESC")->fetchAll();
$roles=$pdo->query("SELECT role_name,description FROM admin_roles ORDER BY role_name")->fetchAll();
$page_title='Staff Management';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';?>
<div class="page-head"><div><span class="eyebrow">SUPER ADMIN</span><h1>Staff Management</h1><p class="muted">Create staff/admin accounts with controlled roles.</p></div></div>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="card"><h2>Create Staff Account</h2><form class="form-grid" method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><label>Name<input name="name" required></label><label>Email<input type="email" name="email" required></label><label>Mobile<input name="mobile"></label><label>Role<select name="role" required><?php foreach($roles as $r):?><option value="<?=e($r['role_name'])?>"><?=e($r['role_name'])?></option><?php endforeach;?></select></label><label>Password (10+ chars)<input type="password" name="password" minlength="10" required></label><div class="form-actions"><button class="btn btn-primary">Create Staff</button></div></form></div>
<div class="card"><h2>Current Staff</h2><div class="table-wrap"><table><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th></tr><?php foreach($staff as $a):?><tr><td><?=e($a['name'])?><br><small><?=e($a['admin_uid'])?></small></td><td><?=e($a['email'])?></td><td><span class="tag"><?=e($a['role'])?></span></td><td><?=e($a['status'])?></td><td><?=e($a['last_login']?:'Never')?></td></tr><?php endforeach;?></table></div></div>
<div class="card"><h2>Role Scope</h2><?php foreach($roles as $r):?><p><b><?=e($r['role_name'])?></b> — <?=e($r['description'])?></p><?php endforeach;?></div>
<?php require '../includes/footer.php';?>
