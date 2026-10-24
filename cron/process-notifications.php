<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/notifier.php';
if(PHP_SAPI!=='cli' && NOTIFICATION_WORKER_SECRET!=='' && !hash_equals(NOTIFICATION_WORKER_SECRET,(string)($_GET['key']??''))){http_response_code(403);exit('Forbidden');}
$pdo=db();
$limit=25;
$sql="SELECT q.id,q.notification_id,q.channel,q.attempts,n.user_id,n.title,n.message,n.job_id,u.email,u.mobile
      FROM notification_queue q JOIN notifications n ON n.id=q.notification_id JOIN users u ON u.id=n.user_id
      WHERE q.status='queued' AND q.attempts < ? ORDER BY q.queued_at ASC LIMIT $limit";
$st=$pdo->prepare($sql);$st->execute([NOTIFICATION_MAX_ATTEMPTS]);$rows=$st->fetchAll();$done=0;$failed=0;
foreach($rows as $q){
    $pdo->prepare("UPDATE notification_queue SET attempts=attempts+1,status='processing' WHERE id=? AND status='queued'")->execute([(int)$q['id']]);
    $result=$q['channel']==='email'?send_email_notification($q,$q):($q['channel']==='whatsapp'?send_whatsapp_notification($q,$q):['ok'=>false,'error'=>'Unsupported queue channel']);
    $provider=$result['provider']??'';$messageId=$result['message_id']??'';$err=$result['error']??'';
    $pdo->prepare("INSERT INTO notification_logs(notification_id,provider,provider_message_id,delivery_status,error_message,sent_at) VALUES(?,?,?,?,?,NOW())")->execute([(int)$q['notification_id'],$provider,$messageId,$result['ok']?'sent':'failed',$err]);
    if($result['ok']){
        $pdo->prepare("UPDATE notification_queue SET status='sent',provider=?,processed_at=NOW(),last_error=NULL WHERE id=?")->execute([$provider,(int)$q['id']]); $done++;
    }else{
        $next=((int)$q['attempts']+1)>=NOTIFICATION_MAX_ATTEMPTS?'failed':'queued';
        $pdo->prepare("UPDATE notification_queue SET status=?,provider=?,last_error=?,processed_at=CASE WHEN attempts>=? THEN NOW() ELSE NULL END WHERE id=?")->execute([$next,$provider,$err,NOTIFICATION_MAX_ATTEMPTS,(int)$q['id']]); $failed++;
    }
}
if(PHP_SAPI==='cli'){echo json_encode(['processed'=>count($rows),'sent'=>$done,'failed_or_requeued'=>$failed]).PHP_EOL;exit;}
header('Content-Type: application/json'); echo json_encode(['processed'=>count($rows),'sent'=>$done,'failed_or_requeued'=>$failed]);
