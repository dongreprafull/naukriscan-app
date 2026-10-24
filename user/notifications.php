<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_user(); $pdo=db(); $uid=(int)$_SESSION['user_id'];
if($_SERVER['REQUEST_METHOD']==='POST' && csrf_check($_POST['_csrf']??'')){
    $id=(int)($_POST['id']??0);
    $pdo->prepare("UPDATE notifications SET read_at=COALESCE(read_at,NOW()),status='read' WHERE id=? AND user_id=?")->execute([$id,$uid]);
    redirect('notifications.php');
}
$st=$pdo->prepare("SELECT n.*,j.job_title,j.organization FROM notifications n LEFT JOIN jobs j ON j.id=n.job_id WHERE n.user_id=? ORDER BY n.created_at DESC");
$st->execute([$uid]);$rows=$st->fetchAll();$page_title='Notifications';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';?>
<div class="page-head"><div><span class="eyebrow">ALERTS</span><h1>Notifications</h1><p class="muted">Job matches and important portal alerts.</p></div></div>
<?php foreach($rows as $n):?><div class="card notification"><h3><?=e($n['title'])?></h3><p><?=e($n['message'])?></p><?php if($n['job_id']):?><p><a class="btn btn-small" href="job-details.php?id=<?=e($n['job_id'])?>">View Job</a></p><?php endif;?><small><?=e($n['created_at'])?> · <?=e($n['status'])?></small><?php if(!$n['read_at']):?><form method="post" class="inline"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=e($n['id'])?>"><button class="btn btn-small">Mark as read</button></form><?php endif;?></div><?php endforeach;?>
<?php if(!$rows):?><div class="empty">No notifications yet.</div><?php endif;?><?php require '../includes/footer.php';?>
