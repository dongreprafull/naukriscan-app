<?php
/**
 * V7 Advanced Eligibility Engine.
 * This is an assistance engine only; the official recruitment notification remains authoritative.
 */
function eg_norm(string $v): string { return strtolower(trim(preg_replace('/\s+/', ' ', $v))); }
function eg_list(string $v): array {
    $v=trim($v); if($v==='') return [];
    $decoded=json_decode($v,true); if(is_array($decoded)) $v=implode(',',array_map('strval',$decoded));
    return array_values(array_filter(array_map(fn($x)=>eg_norm($x), preg_split('/[,;|\n]+/',$v))));
}
function eg_age_relaxation(?string $rules, array $profile): int {
    $cat=eg_norm($profile['category']??''); $rel=0; $decoded=json_decode((string)$rules,true);
    if(is_array($decoded)) {
        foreach($decoded as $key=>$value) {
            if(is_string($value) && eg_norm((string)$key)===$cat) $rel=max($rel,(int)$value);
        }
        foreach(['sc/st','sc','st','obc','ews','pwd','ex_serviceman','ex-serviceman'] as $key) {
            if(isset($decoded[$key]) && is_numeric($decoded[$key])) {
                $matches = in_array($key,['pwd'],true) ? !empty($profile['pwd_status']) : (in_array($key,['ex_serviceman','ex-serviceman'],true) ? !empty($profile['ex_serviceman']) : str_contains($cat,eg_norm($key)));
                if($matches) $rel=max($rel,(int)$decoded[$key]);
            }
        }
    }
    if($rel===0) {
        foreach(eg_list((string)$rules) as $item) if(preg_match('/^(sc|st|sc\/st|obc|ews|pwd|ex[-_ ]serviceman)\s*[:=-]\s*(\d+)/i',$item,$m)) {
            $k=eg_norm($m[1]); $ok=($k==='pwd'&&!empty($profile['pwd_status']))||(($k==='ex-serviceman'||$k==='ex serviceman'||$k==='ex_serviceman')&&!empty($profile['ex_serviceman']))||str_contains($cat,str_replace('/','',$k));
            if($ok) $rel=max($rel,(int)$m[2]);
        }
    }
    return $rel;
}
function eg_has_category(string $allowed, string $category): ?bool {
    $items=eg_list($allowed); if(!$items) return null;
    $cat=eg_norm($category); if($cat==='') return null;
    foreach($items as $item) if($item==='all'||$item==='any'||$item===$cat) return true;
    return false;
}
function eg_user_experience_months(PDO $pdo,int $userId): int {
    $st=$pdo->prepare("SELECT start_date,end_date,currently_working FROM user_experience WHERE user_id=?"); $st->execute([$userId]);
    $months=0; $today=new DateTimeImmutable('today');
    foreach($st->fetchAll() as $x){ if(empty($x['start_date'])) continue; try{$start=new DateTimeImmutable($x['start_date']);$end=!empty($x['currently_working'])?$today:(!empty($x['end_date'])?new DateTimeImmutable($x['end_date']):null); if($end && $end>$start){$d=$start->diff($end);$months += $d->y*12+$d->m+(int)floor($d->d/30);}}catch(Throwable $e){} }
    return $months;
}
function eg_education_blob(PDO $pdo,int $userId): string {
    $st=$pdo->prepare("SELECT qualification_type,course_name,stream,board_university,institution FROM user_education WHERE user_id=?");$st->execute([$userId]);
    return eg_norm(implode(' ',array_map(fn($r)=>implode(' ',array_filter([$r['qualification_type'],$r['course_name'],$r['stream'],$r['board_university'],$r['institution']])), $st->fetchAll())));
}
function eg_qualification_check(string $required,string $blob,?string $structured): array {
    $req=eg_norm(trim($structured?:$required)); if($req==='') return ['pass'=>true,'review'=>false,'reason'=>'No structured qualification restriction was provided.','mismatch'=>''];
    if($blob==='') return ['pass'=>false,'review'=>true,'reason'=>'','mismatch'=>'Education records are missing; qualification cannot be verified.'];
    $groups=[
      ['post graduation','postgraduate','master','m.tech','m.e','mba','mca','msc','ma','m.com'],
      ['graduation','graduate','bachelor','b.tech','b.e','bsc','b.a','b.com','llb','degree'],
      ['diploma','polytechnic'], ['iti','industrial training institute'], ['12th','senior secondary','hsc'], ['10th','matric','ssc']
    ];
    $found=false; foreach($groups as $group){$reqHas=false;$blobHas=false;foreach($group as $k){$reqHas=$reqHas||str_contains($req,$k);$blobHas=$blobHas||str_contains($blob,$k);}if($reqHas&&$blobHas){$found=true;break;}}
    if($found) return ['pass'=>true,'review'=>false,'reason'=>'Your education record appears to meet the qualification level stated in the job rules.','mismatch'=>''];
    return ['pass'=>false,'review'=>true,'reason'=>'','mismatch'=>'The qualification wording needs human verification against the official notification.'];
}
function calculate_job_match(PDO $pdo,int $userId,int $jobId): array {
    $st=$pdo->prepare("SELECT p.*, TIMESTAMPDIFF(YEAR,p.date_of_birth,CURDATE()) age FROM user_profiles p WHERE p.user_id=?");$st->execute([$userId]);$p=$st->fetch()?:[];
    $st=$pdo->prepare("SELECT * FROM jobs WHERE id=? AND status='published' LIMIT 1");$st->execute([$jobId]);$job=$st->fetch();
    if(!$job)return ['status'=>'not_eligible','score'=>0,'reasons'=>[],'mismatches'=>['Job is not published.'],'review_flags'=>[]];
    $st=$pdo->prepare("SELECT * FROM job_eligibility WHERE job_id=? LIMIT 1");$st->execute([$jobId]);$e=$st->fetch()?:[];
    $reasons=[];$mismatches=[];$reviews=[];$checks=[];
    $add=function($name,$state,$reason='',$mismatch='',$review='')use(&$checks,&$reasons,&$mismatches,&$reviews){$checks[]=$state;if($state===true&&$reason)$reasons[]=$reason;if($state===false&&$mismatch)$mismatches[]=$mismatch;if($review)$reviews[]=$review;};
    if($e['min_age']!==null||$e['max_age']!==null){$age=$p['age']??null;if($age===null){$add('age',null,'','','Date of birth is missing.');}else{$rel=eg_age_relaxation($e['age_relaxation_rules']??'', $p);$min=$e['min_age']===null?0:(int)$e['min_age'];$max=$e['max_age']===null?200:(int)$e['max_age']+$rel;$ok=$age>=$min&&$age<=$max;$add('age',$ok,$ok?"Age is within the published range including applicable relaxation.":'', $ok?'':"Age is outside the published range after applicable relaxation.");}}
    $cat=eg_has_category($e['allowed_categories']??'',(string)($p['category']??''));if($cat!==null)$add('category',$cat,$cat?'Your category is listed in the job eligibility rules.':'', $cat?'':'Your category is not listed in the structured job eligibility rules.');
    if(trim((string)($e['pwd_rules']??''))!==''||trim((string)($e['pwd_eligibility']??''))!==''){
        $req=eg_norm((string)($e['pwd_eligibility']??$e['pwd_rules']));$isReq=!in_array($req,['','not applicable','no','none','all'],true);
        if($isReq&&!empty($p['pwd_status'])){ $min=(float)($e['pwd_min_percentage']??0);$pct=(float)($p['pwd_percentage']??0);$ok=$min<=0||$pct>=$min;$add('pwd',$ok,$ok?'PwD status and recorded percentage meet the structured rule.':'','', $ok?'': 'PwD percentage/rule requires verification.'); }
        elseif($isReq&&!empty($p['pwd_status'])===false)$add('pwd',false,'','PwD eligibility is required by the job rules but the candidate profile does not show PwD status.');
    }
    if(trim((string)($e['ex_serviceman_rule']??''))!==''){ $req=eg_norm($e['ex_serviceman_rule']); if(!in_array($req,['no','not applicable','none','all'],true))$add('ex',!empty($p['ex_serviceman']),!empty($p['ex_serviceman'])?'Ex-serviceman status is present in your profile.':'','',!empty($p['ex_serviceman'])?'':'The job has an ex-serviceman condition that needs verification.'); }
    if(trim((string)($e['gender_requirement']??''))!==''&&!in_array(eg_norm($e['gender_requirement']),['all','all genders','any'],true)){ $ok=str_contains(eg_norm($e['gender_requirement']),eg_norm($p['gender']??''));$add('gender',$ok,$ok?'Gender requirement appears compatible.':'','Gender requirement does not appear compatible with your profile.'); }
    if(trim((string)($e['nationality_requirement']??''))!==''){ $req=eg_norm($e['nationality_requirement']);$nat=eg_norm($p['nationality']??'');$ok=$nat!==''&&(str_contains($req,$nat)||($nat==='indian'&&str_contains($req,'indian')));$add('nationality',$ok,$ok?'Nationality appears compatible.':'','Nationality requirement needs verification.'); }
    $dom=trim((string)($e['domicile_states']??''));if($dom!==''){$states=eg_list($dom);$candidate=eg_norm($p['domicile_state']??'');$ok=$candidate!==''&&in_array($candidate,$states,true);$add('domicile',$ok,$ok?'Your domicile state is listed as eligible.':'','Domicile/state requirement is not satisfied by the current profile.');}
    if(trim((string)($e['education_required']??''))!==''||trim((string)($e['qualification_rules']??''))!==''){$q=eg_qualification_check((string)($e['education_required']??''),eg_education_blob($pdo,$userId),(string)($e['qualification_rules']??''));$add('education',$q['pass'],$q['reason'],$q['mismatch'],$q['review']?'Qualification equivalence needs human verification.':'');}
    $expMin=$e['minimum_experience_months']??null;if($expMin!==null){$months=eg_user_experience_months($pdo,$userId);$ok=$months>=(int)$expMin;$add('experience',$ok,$ok?'Recorded experience meets the minimum months rule.':'','Minimum experience is not met based on the recorded experience.');}
    if(trim((string)($e['other_conditions']??''))!==''||trim((string)($e['eligibility_review_notes']??''))!=='')$reviews[]='Other/special conditions must be checked against the official notification.';
    $known=array_filter($checks,fn($x)=>$x!==null);$passed=count(array_filter($known,fn($x)=>$x===true));$score=count($known)?round(($passed/count($known))*100,2):50;
    if($mismatches)$status='not_eligible';elseif($reviews||in_array(null,$checks,true))$status='needs_review';elseif($checks)$status='eligible';else{$status='possible';$reasons[]='Eligibility rules are not sufficiently structured yet.';}
    $engine='rules-v2.0';
    try{$st=$pdo->prepare("INSERT INTO job_matches(job_id,user_id,eligibility_status,match_score,match_reasons,mismatch_reasons,matched_at,engine_version) VALUES(?,?,?,?,?,?,NOW(),?) ON DUPLICATE KEY UPDATE eligibility_status=VALUES(eligibility_status),match_score=VALUES(match_score),match_reasons=VALUES(match_reasons),mismatch_reasons=VALUES(mismatch_reasons),matched_at=NOW(),engine_version=VALUES(engine_version)");$st->execute([$jobId,$userId,$status,$score,implode("\n",$reasons),implode("\n",$mismatches),$engine]);$st=$pdo->prepare("INSERT INTO eligibility_evaluations(user_id,job_id,status,score,engine_version,reasons,mismatches,review_flags,evaluated_at) VALUES(?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),score=VALUES(score),engine_version=VALUES(engine_version),reasons=VALUES(reasons),mismatches=VALUES(mismatches),review_flags=VALUES(review_flags),evaluated_at=NOW()");$st->execute([$userId,$jobId,$status,$score,$engine,implode("\n",$reasons),implode("\n",$mismatches),implode("\n",$reviews)]);}catch(Throwable $ignored){}
    return ['status'=>$status,'score'=>$score,'reasons'=>$reasons,'mismatches'=>$mismatches,'review_flags'=>$reviews];
}
