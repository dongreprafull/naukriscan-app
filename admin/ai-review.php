<?php
require_once '../config/app.php'; require_once '../includes/security.php';
require_once '../includes/ai_extractor.php';
start_secure_session(); require_permission('ai.review'); $pdo=db(); $error=null; $result=null; $logId=null;

function ai_normalize_date($v) {
    $v=trim((string)$v); if($v==='') return null;
    $t=strtotime($v); return $t?date('Y-m-d',$t):null;
}
function ai_apply_extraction(PDO $pdo,int $jobId,array $x,int $adminId,int $logId) {
    $pdo->beginTransaction();
    try {
        $oldSt=$pdo->prepare("SELECT * FROM jobs WHERE id=? FOR UPDATE"); $oldSt->execute([$jobId]); $old=$oldSt->fetch();
        if(!$old) throw new RuntimeException('Job not found.');
        $pick=function($key,$fallback='')use($x){$v=$x[$key]??null; return ($v===null||trim((string)$v)==='')?$fallback:trim((string)$v);};
        $org=$pick('organization',$old['organization']); $dept=$pick('department',$old['department']); $title=$pick('job_title',$old['job_title']);
        $type=$pick('job_type',$old['job_type']); $loc=$pick('location',$old['location']); $state=$pick('state',$old['state']); $district=$pick('district',$old['district']);
        $vac=isset($x['vacancy_count'])&&$x['vacancy_count']!==null?(int)$x['vacancy_count']:(int)$old['vacancy_count'];
        $source=$pick('official_notification_url',$old['source_url']); $app=$pick('official_application_url',$old['application_url']);
        $st=$pdo->prepare("UPDATE jobs SET organization=?,department=?,job_title=?,job_type=?,location=?,state=?,district=?,vacancy_count=?,source_url=?,application_url=?,closing_date=? WHERE id=?");
        $st->execute([$org,$dept,$title,$type,$loc,$state,$district,$vac,$source,$app,ai_normalize_date($x['application_end']??$old['closing_date']),$jobId]);

        $st=$pdo->prepare("SELECT * FROM job_eligibility WHERE job_id=? LIMIT 1 FOR UPDATE"); $st->execute([$jobId]); $e=$st->fetch();
        $g=function($k,$fallback='')use($x,$e){$v=$x[$k]??null; return ($v===null||trim((string)$v)==='')?$fallback:trim((string)$v);};
        $vals=[
            $x['min_age']??($e['min_age']??null),$x['max_age']??($e['max_age']??null),
            $g('education_required',$e['education_required']??''),$g('experience_required',$e['experience_required']??''),
            $g('gender_requirement',$e['gender_requirement']??''),$g('nationality_requirement',$e['nationality_requirement']??'Indian'),
            $g('domicile_requirement',$e['domicile_requirement']??''),$g('category_rules',$e['category_rules']??''),$g('pwd_rules',$e['pwd_rules']??''),
            trim($g('other_conditions',$e['other_conditions']??'')."\n".$g('ex_serviceman_rules','')),
            $g('age_relaxation_rules',$e['age_relaxation_rules']??''),$g('allowed_categories',$e['allowed_categories']??''),
            $x['pwd_min_percentage']??($e['pwd_min_percentage']??null),$g('pwd_eligibility',$e['pwd_eligibility']??''),$g('ex_serviceman_rules',$e['ex_serviceman_rule']??''),
            $g('domicile_states',$e['domicile_states']??''),$g('qualification_rules',$e['qualification_rules']??''),$x['minimum_experience_months']??($e['minimum_experience_months']??null),
            $g('experience_notes',$e['experience_notes']??''),$g('eligibility_review_notes',$e['eligibility_review_notes']??'AI extraction requires human verification.')
        ];
        if($e){$vals[]=$jobId;$pdo->prepare("UPDATE job_eligibility SET min_age=?,max_age=?,education_required=?,experience_required=?,gender_requirement=?,nationality_requirement=?,domicile_requirement=?,category_rules=?,pwd_rules=?,other_conditions=?,age_relaxation_rules=?,allowed_categories=?,pwd_min_percentage=?,pwd_eligibility=?,ex_serviceman_rule=?,domicile_states=?,qualification_rules=?,minimum_experience_months=?,experience_notes=?,eligibility_review_notes=? WHERE job_id=?")->execute($vals);}
        else {array_unshift($vals,$jobId);$pdo->prepare("INSERT INTO job_eligibility(job_id,min_age,max_age,education_required,experience_required,gender_requirement,nationality_requirement,domicile_requirement,category_rules,pwd_rules,other_conditions,age_relaxation_rules,allowed_categories,pwd_min_percentage,pwd_eligibility,ex_serviceman_rule,domicile_states,qualification_rules,minimum_experience_months,experience_notes,eligibility_review_notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute($vals);}

        $st=$pdo->prepare("SELECT id FROM job_dates WHERE job_id=? LIMIT 1 FOR UPDATE"); $st->execute([$jobId]); $did=$st->fetchColumn();
        $d=[ai_normalize_date($x['notification_date']??null),ai_normalize_date($x['application_start']??null),ai_normalize_date($x['application_end']??null),ai_normalize_date($x['fee_last_date']??null),ai_normalize_date($x['exam_date']??null),ai_normalize_date($x['admit_card_date']??null),ai_normalize_date($x['result_date']??null)];
        if($did){$d[]=$jobId;$pdo->prepare("UPDATE job_dates SET notification_date=COALESCE(?,notification_date),application_start=COALESCE(?,application_start),application_end=COALESCE(?,application_end),fee_last_date=COALESCE(?,fee_last_date),exam_date=COALESCE(?,exam_date),admit_card_date=COALESCE(?,admit_card_date),result_date=COALESCE(?,result_date) WHERE job_id=?")->execute($d);}
        else {array_unshift($d,$jobId);$pdo->prepare("INSERT INTO job_dates(job_id,notification_date,application_start,application_end,fee_last_date,exam_date,admit_card_date,result_date) VALUES(?,?,?,?,?,?,?,?)")->execute($d);}

        $pdo->prepare("UPDATE ai_extraction_logs SET status='verified',reviewed_at=NOW(),reviewed_by=?,applied_at=NOW(),applied_by=? WHERE id=? AND status='review'")->execute([$adminId,$adminId,$logId]);
        if($pdo->lastInsertId()===false){} // keep PDO compatibility
        $pdo->prepare("INSERT INTO audit_logs(admin_id,action,module,record_id,old_value,new_value,ip_address,user_agent,created_at) VALUES(?,?,?,?,?,?,?,?,NOW())")
            ->execute([$adminId,'AI_VERIFY_APPLY','jobs',$jobId,json_encode($old,JSON_UNESCAPED_UNICODE),json_encode(['job_id'=>$jobId,'log_id'=>$logId],JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR']??'',substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]);
        $pdo->commit(); return true;
    } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack(); throw $e;}
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!csrf_check($_POST['_csrf']??'')) $error='Invalid request.';
    else try {
        $action=$_POST['action']??'';
        if($action==='extract'){
            $jobId=(int)$_POST['job_id']; $text=trim($_POST['notification_text']??''); $sourceType='text'; $sourceRef=null;
            if($text===''){
                $st=$pdo->prepare("SELECT notification_file_path,notification_file_mime,notification_file_name FROM jobs WHERE id=?");$st->execute([$jobId]);$j=$st->fetch();
                if(!$j || empty($j['notification_file_path'])) throw new RuntimeException('Attach a notification PDF first, or paste notification text.');
                $full=realpath(__DIR__.'/../'.$j['notification_file_path']);
                if(!$full || strpos($full,realpath(__DIR__.'/../storage/job-notifications/'))!==0) throw new RuntimeException('Invalid notification file path.');
                if($j['notification_file_mime']==='application/pdf'){ $text=extract_pdf_text_local($full); $sourceType='pdf'; $sourceRef=$j['notification_file_path']; }
                else throw new RuntimeException('JPG/PNG OCR is not enabled in this V5 build. Paste the notification text.');
            }
            $result=ai_extract_notification($text);
            $hash=hash('sha256',$text);
            $st=$pdo->prepare("INSERT INTO ai_extraction_logs(job_id,admin_id,provider,model,source_type,source_reference,source_text,extracted_json,status,source_sha256,prompt_version) VALUES(?,?,?,?,?,?,?,?, 'review',?,?)");
            $st->execute([$jobId,$_SESSION['admin_id'],AI_PROVIDER,AI_MODEL,$sourceType,$sourceRef,$text,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$hash,defined('AI_PROMPT_VERSION')?AI_PROMPT_VERSION:'v2']);
            $logId=(int)$pdo->lastInsertId();
        }
        if($action==='apply'){
            $logId=(int)$_POST['log_id']; $st=$pdo->prepare("SELECT * FROM ai_extraction_logs WHERE id=? AND status='review'");$st->execute([$logId]);$log=$st->fetch();
            if(!$log) throw new RuntimeException('Review record not found.');
            $x=json_decode($log['extracted_json'],true); if(!is_array($x)) throw new RuntimeException('Invalid extraction.');
            $jobId=(int)$log['job_id'];
            // Allow reviewer corrections from the form before applying.
            foreach(['organization','department','job_title','job_type','location','state','district','gender_requirement','nationality_requirement','education_required','experience_required','domicile_requirement','category_rules','pwd_rules','ex_serviceman_rules','other_conditions','official_notification_url','official_application_url'] as $k) if(array_key_exists($k,$_POST)) $x[$k]=trim((string)$_POST[$k]);
            foreach(['vacancy_count','min_age','max_age'] as $k) if(array_key_exists($k,$_POST)) $x[$k]=($_POST[$k]===''?null:(int)$_POST[$k]);
            foreach(['notification_date','application_start','application_end','fee_last_date','exam_date','admit_card_date','result_date'] as $k) if(array_key_exists($k,$_POST)) $x[$k]=$_POST[$k]?:null;
            $pdo->prepare("UPDATE ai_extraction_logs SET extracted_json=? WHERE id=?")->execute([json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$logId]);
            $_POST['log_id']=$logId; ai_apply_extraction($pdo,$jobId,$x,(int)$_SESSION['admin_id'],$logId); flash('success','AI data reviewed and applied. Job remains Pending Verification.'); redirect('ai-review.php?log='.$logId);
        }
    } catch(Throwable $e){$error=$e->getMessage();}
}
$jobs=$pdo->query("SELECT id,job_uid,job_title,organization,notification_file_name FROM jobs ORDER BY created_at DESC")->fetchAll();
$log=null; if(isset($_GET['log'])){$st=$pdo->prepare("SELECT l.*,j.job_title,j.job_uid FROM ai_extraction_logs l LEFT JOIN jobs j ON j.id=l.job_id WHERE l.id=?");$st->execute([(int)$_GET['log']]);$log=$st->fetch();}
if(!$result && $log && !empty($log['extracted_json'])) $result=json_decode($log['extracted_json'],true);
$queue=$pdo->query("SELECT l.id,l.status,l.created_at,l.source_type,l.source_sha256,j.job_uid,j.job_title FROM ai_extraction_logs l LEFT JOIN jobs j ON j.id=l.job_id ORDER BY l.created_at DESC LIMIT 30")->fetchAll();
$page_title='AI Notification Review';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';?>
<div class="page-head"><div><span class="eyebrow">AI NOTIFICATION ENGINE</span><h1>AI Review</h1><p class="muted">Extract facts from an attached PDF or pasted notification text. AI never publishes a job.</p></div></div>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="card"><h2>Review Queue</h2><div class="table-wrap"><table><tr><th>Job</th><th>Source</th><th>Status</th><th>Created</th><th>Action</th></tr><?php foreach($queue as $q):?><tr><td><?=e($q['job_title'])?><br><small><?=e($q['job_uid'])?></small></td><td><?=e($q['source_type'])?></td><td><?=e($q['status'])?></td><td><?=e($q['created_at'])?></td><td><a class="btn btn-small" href="ai-review.php?log=<?=e($q['id'])?>">Review</a></td></tr><?php endforeach;?></table></div></div>
<div class="grid-2">
<div class="card"><h2>1. Extract</h2><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="extract">
<label>Job<select name="job_id" required><?php foreach($jobs as $j):?><option value="<?=e($j['id'])?>"><?=e($j['job_title'])?> — <?=e($j['job_uid'])?> — <?=e($j['notification_file_name']?:'no file')?></option><?php endforeach;?></select></label>
<label>Notification text (optional if PDF is attached)<textarea name="notification_text" rows="14" placeholder="Paste notification text here. If blank, the attached PDF will be parsed when pdftotext is available."></textarea></label>
<div class="form-actions"><button class="btn btn-primary">Run AI Extraction</button></div></form></div>
<div class="card"><h2>2. Review & Apply</h2>
<?php if($result):?>
<form method="post">
<input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<input type="hidden" name="action" value="apply">
<input type="hidden" name="log_id" value="<?=e($logId ?: ($log['id']??''))?>">
<?php
$fields=['organization','department','job_title','job_type','location','state','district','vacancy_count','min_age','max_age','gender_requirement','nationality_requirement','education_required','experience_required','domicile_requirement','category_rules','pwd_rules','ex_serviceman_rules','other_conditions','notification_date','application_start','application_end','fee_last_date','exam_date','admit_card_date','result_date','official_notification_url','official_application_url'];
$dateFields=['notification_date','application_start','application_end','fee_last_date','exam_date','admit_card_date','result_date'];
$numFields=['vacancy_count','min_age','max_age'];
$textareas=['education_required','experience_required','domicile_requirement','category_rules','pwd_rules','ex_serviceman_rules','other_conditions'];
foreach($fields as $f){
    $v=$result[$f]??'';
    echo '<label>'.e(ucwords(str_replace('_',' ',$f)));
    if(in_array($f,$numFields,true)){
        echo '<input type="number" name="'.e($f).'" value="'.e($v).'">';
    } elseif(in_array($f,$dateFields,true)){
        echo '<input type="date" name="'.e($f).'" value="'.e($v).'">';
    } elseif(in_array($f,$textareas,true)){
        echo '<textarea name="'.e($f).'" rows="3">'.e($v).'</textarea>';
    } elseif(str_ends_with($f,'url')){
        echo '<input type="url" name="'.e($f).'" value="'.e($v).'">';
    } else {
        echo '<input name="'.e($f).'" value="'.e($v).'">';
    }
    echo '</label>';
}
?>
<div class="card"><strong>Confidence:</strong> <?=e($result['confidence']??'')?> <br><strong>Warnings:</strong><ul>
<?php foreach(($result['warnings']??[]) as $w): ?><li><?=e($w)?></li><?php endforeach; ?>
</ul></div>
<button class="btn btn-primary">Verify & Apply to Job</button>
</form>
<?php else:?><p class="muted">Run extraction to see structured fields here.</p><?php endif;?></div>
</div>
<?php require '../includes/footer.php';?>
