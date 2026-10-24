<?php
require_once '../config/app.php'; require_once '../includes/security.php';
start_secure_session(); require_permission('settings.manage'); $pdo=db();
$checks=[];
function ic_add(&$checks,$name,$ok,$detail){$checks[]=['name'=>$name,'ok'=>(bool)$ok,'detail'=>$detail];}
try { $pdo->query('SELECT 1'); ic_add($checks,'Database connection',true,'MySQL connection succeeded.'); } catch(Throwable $e){ ic_add($checks,'Database connection',false,'Database connection failed.'); }
$tables=['users','user_profiles','user_documents','user_education','user_experience','jobs','job_eligibility','job_dates','job_matches','eligibility_evaluations','notifications','notification_preferences','notification_queue','subscription_plans','subscriptions','payments','application_templates','application_template_fields','application_assistance_records','ai_extraction_logs','admin_roles','permissions','admin_role_permissions','audit_logs','login_attempts','privacy_consents','account_deletion_requests','integration_check_runs'];
foreach($tables as $t){try{$st=$pdo->prepare('SHOW TABLES LIKE ?');$st->execute([$t]);$present=(bool)$st->fetchColumn();ic_add($checks,'Table: '.$t,$present,$present?'Present':'Missing');}catch(Throwable $e){ic_add($checks,'Table: '.$t,false,'Unable to inspect table.');}}
$paths=['storage/documents','storage/ai','storage/job-notifications'];
foreach($paths as $rel){$path=realpath(__DIR__.'/../'.$rel);ic_add($checks,'Storage: '.$rel,(bool)$path && is_dir($path) && is_writable($path),($path && is_dir($path))?(is_writable($path)?'Directory writable':'Directory not writable'):'Directory missing');}
ic_add($checks,'HTTPS',(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),'Production should use HTTPS.');
ic_add($checks,'AI configuration',defined('OPENAI_API_KEY') && OPENAI_API_KEY!=='','API key is configured server-side.');
$summary=['total'=>count($checks),'passed'=>count(array_filter($checks,fn($x)=>$x['ok']))];$summary['status']=$summary['passed']===$summary['total']?'pass':'attention';
try{$st=$pdo->prepare('INSERT INTO integration_check_runs(admin_id,status,checks_json) VALUES(?,?,?)');$st->execute([$_SESSION['admin_id'],$summary['status'],json_encode($checks,JSON_UNESCAPED_UNICODE)]);}catch(Throwable $e){}
$page_title='Integration Check';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';?>
<div class="page-head"><div><span class="eyebrow">V18 PRODUCTION QA</span><h1>Integration Check</h1><p class="muted">Run this after every Hostinger deployment or database migration.</p></div></div>
<div class="card"><h2><?=e($summary['passed'])?> / <?=e($summary['total'])?> checks passed</h2><div class="table-wrap"><table><tr><th>Check</th><th>Status</th><th>Details</th></tr><?php foreach($checks as $c):?><tr><td><?=e($c['name'])?></td><td><span class="tag"><?= $c['ok']?'PASS':'ATTENTION' ?></span></td><td><?=e($c['detail'])?></td></tr><?php endforeach;?></table></div></div>
<?php require '../includes/footer.php';?>
