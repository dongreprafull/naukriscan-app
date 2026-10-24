<?php
require_once '../config/app.php'; require_once '../includes/security.php'; require_once '../includes/application_assistance.php'; start_secure_session(); require_user(); $pdo=db(); $uid=(int)$_SESSION['user_id']; $jobid=(int)($_GET['job']??$_POST['job_id']??0);
$st=$pdo->prepare("SELECT j.*,t.id template_id,t.template_name,t.official_form_url,t.instructions,t.status template_status FROM jobs j LEFT JOIN application_templates t ON t.job_id=j.id AND t.status='active' WHERE j.id=? AND j.status='published' LIMIT 1");$st->execute([$jobid]);$job=$st->fetch();if(!$job)redirect('jobs.php');
if(empty($job['template_id'])) { flash('error','Application assistance is not configured for this job yet.'); redirect('job-details.php?id='.$jobid); }
$tid=(int)$job['template_id'];$st=$pdo->prepare('SELECT * FROM application_template_fields WHERE template_id=? ORDER BY sort_order,id');$st->execute([$tid]);$fields=$st->fetchAll();$ctx=load_candidate_context($pdo,$uid);$values=[];
foreach($fields as $f){$v=resolve_profile_source($ctx,$f['profile_source']);if($v==='' && $f['input_type']==='document')$v=document_source_value($ctx,$f['profile_source']);$values[$f['field_key']]=$v;}
$st=$pdo->prepare('SELECT * FROM applications WHERE user_id=? AND job_id=? LIMIT 1');$st->execute([$uid,$jobid]);$app=$st->fetch();
if(!$app){$pdo->prepare("INSERT INTO applications(user_id,job_id,application_status,official_application_url,assistance_started_at,last_assistance_at) VALUES(?,?,?,?,NOW(),NOW())")->execute([$uid,$jobid,'started',$job['official_form_url']?:$job['application_url']]);$appId=(int)$pdo->lastInsertId();}
else{$appId=(int)$app['id'];$pdo->prepare('UPDATE applications SET application_status=IF(application_status="not_started","started",application_status),assistance_started_at=COALESCE(assistance_started_at,NOW()),last_assistance_at=NOW(),official_application_url=? WHERE id=?')->execute([$job['official_form_url']?:$job['application_url'],$appId]);}
$error=null;$saved=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_check($_POST['_csrf']??''))$error='Invalid request.';
 else {
  foreach($fields as $f){$key=$f['field_key'];$values[$key]=is_array($_POST[$key]??null)?'':trim((string)($_POST[$key]??''));}
  $missing=[];foreach($fields as $f)if($f['required'] && $values[$f['field_key']]==='')$missing[]=$f['field_label'];
  $check=[];foreach($ctx['documents'] as $d)$check[$d['document_type']]=$d['verification_status'];
  $pdo->prepare("INSERT INTO application_assistance_records(application_id,template_id,candidate_data_json,missing_fields_json,checklist_json,reviewed_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE candidate_data_json=VALUES(candidate_data_json),missing_fields_json=VALUES(missing_fields_json),checklist_json=VALUES(checklist_json),reviewed_at=NOW(),updated_at=NOW()")
   ->execute([$appId,$tid,json_encode($values,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),json_encode($missing),json_encode($check)]);
  $pdo->prepare("UPDATE applications SET application_status=?,last_assistance_at=NOW() WHERE id=?")->execute([$missing?'started':'assisted',$appId]);
  $saved=true;
 }
}
$page_title='Application Assistance';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">APPLICATION ASSISTANCE</span><h1><?=e($job['job_title'])?></h1><p class="muted"><?=e($job['organization'])?> · <?=e($job['template_name'])?></p></div></div>
<?php if($saved):?><div class="alert success">Your assisted application data has been saved. Review everything on the official recruitment website before submitting.</div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="card"><p><?=nl2br(e($job['instructions']??''))?></p><div class="alert">This tool prepares information from your profile. You must review it and complete CAPTCHA/OTP and submit/pay on the official recruitment website.</div></div>
<form class="card" method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="job_id" value="<?=$jobid?>"><h2>Review Application Data</h2><div class="form-grid">
<?php foreach($fields as $f): $key=$f['field_key']; $val=$values[$key]??''; $type=$f['input_type']; ?><label><?=e($f['field_label'])?><?=!empty($f['required'])?' *':''?><?php if($type==='textarea'):?><textarea name="<?=e($key)?>" rows="3" <?=!empty($f['required'])?'required':''?>><?=e($val)?></textarea><?php elseif($type==='select'):?><select name="<?=e($key)?>" <?=!empty($f['required'])?'required':''?>><option value="">Select</option><?php $opts=json_decode($f['options_json']??'[]',true)?:[];foreach($opts as $o):?><option value="<?=e($o)?>" <?=$val===$o?'selected':''?>><?=e($o)?></option><?php endforeach;?></select><?php elseif($type==='checkbox'):?><input type="checkbox" name="<?=e($key)?>" value="1" <?=$val?'checked':''?>><?php elseif($type==='date'):?><input type="date" name="<?=e($key)?>" value="<?=e($val)?>" <?=!empty($f['required'])?'required':''?>><?php elseif($type==='number'):?><input type="number" name="<?=e($key)?>" value="<?=e($val)?>" <?=!empty($f['required'])?'required':''?>><?php elseif($type==='document'):?><input type="text" name="<?=e($key)?>" value="<?=e($val)?>" readonly><?php else:?><input name="<?=e($key)?>" value="<?=e($val)?>" <?=!empty($f['required'])?'required':''?>><?php endif;?><?php if($f['help_text']):?><small class="muted"><?=e($f['help_text'])?></small><?php endif;?></label><?php endforeach;?></div><div class="form-actions"><button class="btn btn-primary">Save & Review</button><a class="btn btn-light" href="<?=e($job['official_form_url']?:$job['application_url'])?>" target="_blank" rel="noopener">Open Official Application ↗</a></div></form>
<?php require '../includes/footer.php';?>
