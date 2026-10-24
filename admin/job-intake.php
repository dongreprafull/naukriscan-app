<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_permission('jobs.create'); $pdo=db(); $error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_check($_POST['_csrf']??'')) $error='Invalid request.';
 else try{
  $action=$_POST['action']??'';
  if($action==='source'){
   $pdo->prepare("INSERT INTO job_sources(source_name,source_url,source_type,active,created_at) VALUES(?,?,?,?,NOW())")
     ->execute([trim($_POST['source_name']),trim($_POST['source_url']),trim($_POST['source_type']),1]);
   flash('success','Job source added.'); redirect('job-intake.php');
  }
  if($action==='note'){
   $id=(int)$_POST['job_id'];
   if(!isset($_FILES['notification']) || $_FILES['notification']['error']!==UPLOAD_ERR_OK) throw new Exception('file');
   $f=$_FILES['notification']; $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
   $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
   if(!isset($allowed[$mime]) || $f['size']>10*1024*1024) throw new Exception('type');
   $dir=__DIR__.'/../storage/job-notifications/'.$id; if(!is_dir($dir)) mkdir($dir,0750,true);
   $name='notification-'.bin2hex(random_bytes(10)).'.'.$allowed[$mime]; $path=$dir.'/'.$name;
   if(!move_uploaded_file($f['tmp_name'],$path)) throw new Exception('move');
   $relative='storage/job-notifications/'.$id.'/'.$name;
   $pdo->prepare("UPDATE jobs SET notification_file_path=?,notification_file_name=?,notification_file_mime=? WHERE id=?")
     ->execute([$relative,basename($f['name']),$mime,$id]);
   flash('success','Official notification file attached to the job.'); redirect('job-intake.php');
  }
 }catch(Throwable $e){$error='Could not save this intake item. Check the file type/size and fields.';}
}
$jobs=$pdo->query("SELECT id,job_uid,job_title,organization,status,notification_file_name FROM jobs ORDER BY created_at DESC")->fetchAll();
$sources=$pdo->query("SELECT * FROM job_sources ORDER BY created_at DESC")->fetchAll();
$page_title='Job Intake';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';?>
<div class="page-head"><div><span class="eyebrow">STAFF WORKFLOW</span><h1>Job Intake</h1><p class="muted">Keep source links and official notifications together before a recruitment is published.</p></div></div>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="grid-2">
<div class="card"><h2>Add Government Job Source</h2><form class="form-grid" method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="source"><label>Source Name<input name="source_name" placeholder="UPSC / SSC / State PSC" required></label><label>Official URL<input type="url" name="source_url" required></label><label>Source Type<input name="source_type" placeholder="Central / State / PSU / Other"></label><div class="form-actions"><button class="btn btn-primary">Add Source</button></div></form></div>
<div class="card"><h2>Attach Official Notification</h2><form class="form-grid" method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="note"><label>Job<select name="job_id" required><?php foreach($jobs as $j):?><option value="<?=e($j['id'])?>"><?=e($j['job_title'])?> — <?=e($j['organization'])?></option><?php endforeach;?></select></label><label>PDF/JPG/PNG, max 10 MB<input type="file" name="notification" accept=".pdf,.jpg,.jpeg,.png" required></label><div class="form-actions"><button class="btn btn-primary">Attach Notification</button></div></form></div>
</div>
<div class="card"><h2>Tracked Official Sources</h2><div class="table-wrap"><table><tr><th>Source</th><th>Type</th><th>URL</th><th>Status</th></tr><?php foreach($sources as $s):?><tr><td><?=e($s['source_name'])?></td><td><?=e($s['source_type'])?></td><td><a href="<?=e($s['source_url'])?>" target="_blank" rel="noopener">Open ↗</a></td><td><?=!empty($s['active'])?'Active':'Inactive'?></td></tr><?php endforeach;?></table></div></div>
<div class="card"><h2>Notification Files</h2><div class="table-wrap"><table><tr><th>Job</th><th>Status</th><th>Notification</th></tr><?php foreach($jobs as $j):?><tr><td><?=e($j['job_title'])?><br><small><?=e($j['job_uid'])?></small></td><td><?=e($j['status'])?></td><td><?=e($j['notification_file_name']?:'Not attached')?></td></tr><?php endforeach;?></table></div></div>
<?php require '../includes/footer.php';?>