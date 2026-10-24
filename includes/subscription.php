<?php
function current_subscription(PDO $pdo, int $userId): ?array {
    $st=$pdo->prepare("SELECT s.*,p.plan_name,p.price,p.features,p.is_trial FROM subscriptions s JOIN subscription_plans p ON p.id=s.plan_id WHERE s.user_id=? AND s.status='active' AND s.start_date<=CURDATE() AND s.end_date>=CURDATE() ORDER BY s.end_date DESC,s.id DESC LIMIT 1");
    $st->execute([$userId]); return $st->fetch() ?: null;
}
function has_active_subscription(PDO $pdo,int $userId): bool { return current_subscription($pdo,$userId)!==null; }
function ensure_trial(PDO $pdo,int $userId): ?array {
    $existing=$pdo->prepare("SELECT s.id FROM subscriptions s WHERE s.user_id=? AND s.subscription_type='trial' LIMIT 1");
    $existing->execute([$userId]); if($existing->fetch()) return current_subscription($pdo,$userId);
    $plan=$pdo->query("SELECT * FROM subscription_plans WHERE status='active' AND is_trial=1 ORDER BY sort_order,id LIMIT 1")->fetch();
    if(!$plan) return null;
    $pdo->beginTransaction();
    try {
        $start=new DateTimeImmutable('today'); $end=$start->modify('+'.max(0,(int)$plan['duration_days']-1).' days');
        $st=$pdo->prepare("INSERT INTO subscriptions(user_id,plan_id,start_date,end_date,status,auto_renew,subscription_type,created_at,activated_at) VALUES(?,?,?,?, 'active',0,'trial',NOW(),NOW())");
        $st->execute([$userId,$plan['id'],$start->format('Y-m-d'),$end->format('Y-m-d')]); $pdo->commit();
    } catch(Throwable $e){$pdo->rollBack(); throw $e;}
    return current_subscription($pdo,$userId);
}
function expire_subscriptions(PDO $pdo): void { $pdo->exec("UPDATE subscriptions SET status='expired' WHERE status='active' AND end_date<CURDATE()"); }
