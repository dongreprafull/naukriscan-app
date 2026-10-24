<?php
require_once __DIR__ . '/matching.php';

/**
 * Create in-app job-match notifications after a job is deliberately published.
 * Email/WhatsApp are queued separately in V6 and require provider credentials.
 */
function notify_published_job_matches(PDO $pdo, int $jobId, int $adminId): int {
    $st=$pdo->prepare("SELECT id,job_title,organization,application_url,closing_date FROM jobs WHERE id=? AND status='published' LIMIT 1");
    $st->execute([$jobId]); $job=$st->fetch();
    if(!$job) return 0;

    $users=$pdo->query("SELECT u.id FROM users u WHERE u.account_status='active'")->fetchAll(PDO::FETCH_COLUMN);
    $created=0;
    foreach($users as $uid){
        $uid=(int)$uid;
        try { $match=calculate_job_match($pdo,$uid,$jobId); } catch(Throwable $e) { continue; }
        if(!in_array($match['status'],['eligible','possible'],true)) continue;

        $pref=$pdo->prepare("SELECT in_app_enabled,email_enabled,whatsapp_enabled,job_matches_enabled FROM notification_preferences WHERE user_id=? LIMIT 1");
        $pref->execute([$uid]); $p=$pref->fetch();
        if($p && (!(int)$p['in_app_enabled'] || !(int)$p['job_matches_enabled'])) continue;

        $exists=$pdo->prepare("SELECT id FROM notifications WHERE user_id=? AND job_id=? AND notification_type='job_match' AND channel='in_app' LIMIT 1");
        $exists->execute([$uid,$jobId]);
        if($exists->fetchColumn()) continue;

        $title='New government job match';
        $message=$job['organization'].' — '.$job['job_title'].' matches your profile ('.$match['status'].', '.$match['score'].'%).';
        $ins=$pdo->prepare("INSERT INTO notifications(user_id,notification_type,title,message,job_id,channel,status) VALUES(?,?,?,?,?,'in_app','pending')");
        $ins->execute([$uid,'job_match',$title,$message,$jobId]);
        $nid=(int)$pdo->lastInsertId();
        // In-app notifications are immediately available; delivery queues are only for external channels.
        $pdo->prepare("UPDATE notifications SET status='sent' WHERE id=?")->execute([$nid]);
        if($p && (int)$p['email_enabled']){
            $pdo->prepare("INSERT IGNORE INTO notification_queue(notification_id,channel,status) VALUES(?, 'email','queued')")->execute([$nid]);
        }
        if($p && (int)$p['whatsapp_enabled']){
            $pdo->prepare("INSERT IGNORE INTO notification_queue(notification_id,channel,status) VALUES(?, 'whatsapp','queued')")->execute([$nid]);
        }
        $created++;
    }

    $pdo->prepare("INSERT INTO audit_logs(admin_id,action,module,record_id,new_value,created_at) VALUES(?,?,?,?,?,NOW())")
        ->execute([$adminId,'JOB_MATCH_NOTIFICATIONS','notifications',$jobId,$created.' in-app match notification(s) created']);
    return $created;
}
