<?php
require_once '../config/app.php';require_once '../includes/security.php';start_secure_session();require_user();$pdo=db();$uid=(int)$_SESSION['user_id'];$error=null;
$allowed=['photo'=>'image','signature'=>'image','10th_certificate'=>'pdfimage','12th_certificate'=>'pdfimage','graduation_certificate'=>'pdfimage','marksheet'=>'pdfimage','caste_certificate'=>'pdfimage','ews_certificate'=>'pdfimage','domicile_certificate'=>'pdfimage','pwd_certificate'=>'pdfimage','experience_certificate'=>'pdfimage','other'=>'pdfimage'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_check($_POST['_csrf']??''))$error='Invalid request.';
 elseif(!isset($_FILES['document'])||$_FILES['document']['error']!==UPLOAD_ERR_OK)$error='Please select a valid file.';
 else{
  $type=$_POST['document_type']??'other';$f=$_FILES['document'];$max=5*1024*1024;
  if(!array_key_exists($type,$allowed)){$error='Invalid document type.';}
  elseif($f['size']<=0 || $f['size']>$max){$error='File must be between 1 byte and 5 MB.';}
  else{
  $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($f['tmp_name']);
  $valid=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
  if(!isset($valid[$mime]))$error='Only JPG, PNG or PDF files up to 5 MB are allowed.';
  elseif(($mime==='application/pdf' && file_get_contents($f['tmp_name'],false,null,0,5)!=='%PDF-') || ($mime!=='application/pdf' && @getimagesize($f['tmp_name'])===false))$error='The uploaded file content does not match its declared type.';
  else{try{$dir=__DIR__.'/../storage/documents/'.$uid;if(!is_dir($dir))mkdir($dir,0750,true);$name=bin2hex(random_bytes(16)).'.'.$valid[$mime];$path=$dir.'/'.$name;if(!move_uploaded_file($f['tmp_name'],$path))throw new Exception();$relative='storage/documents/'.$uid.'/'.$name;$pdo->prepare("INSERT INTO user_documents(user_id,document_type,file_name,file_path,file_size,mime_type,verification_status) VALUES(?,?,?,?,?,?, 'pending')")->execute([$uid,$type,preg_replace('/[^A-Za-z0-9._ -]/','_',basename($f['name'])),$relative,(int)$f['size'],$mime]);flash('success','Document uploaded and sent for verification.');redirect('documents.php');}catch(Throwable $e){$error='Upload failed.';}}
  }
 }
}
$st=$pdo->prepare("SELECT * FROM user_documents WHERE user_id=? ORDER BY uploaded_at DESC");$st->execute([$uid]);$rows=$st->fetchAll();$page_title='Documents';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';?>
<div class="page-head"><div><h1>Documents</h1><p class="muted">Upload recruitment-related documents. Admin verification will be shown here.</p></div></div><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="card"><h2>Upload Document</h2><form class="form-grid" method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><label>Document Type<select name="document_type"><?php foreach(array_keys($allowed) as $x):?><option value="<?=e($x)?>"><?=e(ucwords(str_replace('_',' ',$x)))?></option><?php endforeach;?></select></label><label>File (JPG/PNG/PDF, max 5 MB)<input type="file" name="document" accept=".jpg,.jpeg,.png,.pdf" required></label><div class="form-actions"><button class="btn btn-primary">Upload</button></div></form></div>
<div class="card table-wrap"><table><tr><th>Type</th><th>File</th><th>Size</th><th>Status</th><th>Uploaded</th></tr><?php foreach($rows as $r):?><tr><td><?=e(ucwords(str_replace('_',' ',$r['document_type'])))?></td><td><?=e($r['file_name'])?> · <a href="download-document.php?id=<?=e($r['id'])?>">Download</a></td><td><?=e(round($r['file_size']/1024,1))?> KB</td><td><?=e($r['verification_status'])?></td><td><?=e($r['uploaded_at'])?></td></tr><?php endforeach;?></table><?php if(!$rows):?><div class="empty">No documents uploaded yet.</div><?php endif;?></div>
<?php require '../includes/footer.php';?>