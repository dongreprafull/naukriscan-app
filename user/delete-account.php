<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_user();
$pdo=db(); $uid=(int)$_SESSION['user_id']; $error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_check($_POST['_csrf']??'')) $error='Invalid request.';
 else { $reason=trim($_POST['reason']??'');
  $st=$pdo->prepare("SELECT id FROM account_deletion_requests WHERE user_id=? AND status='pending' LIMIT 1"); $st->execute([$uid]);
  if($st->fetch()) $error='A deletion request is already pending.';
  else { $pdo->prepare("INSERT INTO account_deletion_requests(user_id,reason,requested_at) VALUES(?,?,NOW())")->execute([$uid,$reason]); flash('success','Account deletion request submitted.'); redirect('settings.php'); }
 }
}
$page_title='Delete Account';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php'; ?>
<div class="card"><h1>Request Account Deletion</h1><p>This sends a deletion request for review. Some records may need to be retained where required for security, accounting, legal, or audit purposes.</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><label>Reason (optional)<textarea name="reason" maxlength="255"></textarea></label><button class="btn btn-primary">Submit Deletion Request</button></form></div><?php require '../includes/footer.php'; ?>
