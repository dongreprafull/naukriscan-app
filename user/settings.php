<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_user(); $pdo=db(); $error=null;
$uid=(int)$_SESSION['user_id'];
$st=$pdo->prepare("SELECT * FROM notification_preferences WHERE user_id=? LIMIT 1");$st->execute([$uid]);$p=$st->fetch();
if(!$p){
    $pdo->prepare("INSERT INTO notification_preferences(user_id) VALUES(?)")->execute([$uid]);
    $st=$pdo->prepare("SELECT * FROM notification_preferences WHERE user_id=? LIMIT 1");$st->execute([$uid]);$p=$st->fetch();
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_check($_POST['_csrf']??'')) $error='Invalid request.';
    else {
        $vals=[
            isset($_POST['in_app_enabled'])?1:0,
            isset($_POST['email_enabled'])?1:0,
            isset($_POST['job_matches_enabled'])?1:0,
            isset($_POST['deadline_reminders_enabled'])?1:0,
            $uid
        ];
        $pdo->prepare("UPDATE notification_preferences SET in_app_enabled=?,email_enabled=?,job_matches_enabled=?,deadline_reminders_enabled=? WHERE user_id=?")->execute($vals);
        flash('success','Notification preferences saved.'); redirect('settings.php');
    }
}
$success=flash('success'); $page_title='Settings';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';?>
<h1>Settings</h1>
<?php if($success):?><div class="alert success"><?=e($success)?></div><?php endif;?>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="card"><h2>Notification Preferences</h2>
<form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<label><input type="checkbox" name="in_app_enabled" <?=$p['in_app_enabled']?'checked':''?>> In-app notifications</label>
<label><input type="checkbox" name="email_enabled" <?=$p['email_enabled']?'checked':''?>> Email notifications</label>
<label><input type="checkbox" name="job_matches_enabled" <?=$p['job_matches_enabled']?'checked':''?>> New job-match alerts</label>
<label><input type="checkbox" name="deadline_reminders_enabled" <?=$p['deadline_reminders_enabled']?'checked':''?>> Application deadline reminders</label>
<div class="form-actions"><button class="btn btn-primary">Save Preferences</button></div></form></div><div class="card"><h2>Privacy</h2><p><a href="../privacy.php">Privacy Policy</a> · <a href="../terms.php">Terms & Conditions</a> · <a href="delete-account.php">Request Account Deletion</a></p></div>
<?php require '../includes/footer.php';?>
