<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_user(); $pdo=db(); require_once '../includes/subscription.php';
$uid=(int)$_SESSION['user_id']; expire_subscriptions($pdo); $sub=current_subscription($pdo,$uid); $message=null; $error=null;
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='start_trial') {
 if(!csrf_check($_POST['_csrf']??'')){ $error='Invalid request.'; }
 elseif($sub){ $error='You already have an active subscription.'; }
 else { try { $sub=ensure_trial($pdo,$uid); $message=$sub?'Free trial activated.':'Free trial is currently unavailable.'; } catch(Throwable $e){$error='Unable to activate trial right now.';} }
}
$plans=$pdo->query("SELECT * FROM subscription_plans WHERE status='active' ORDER BY sort_order,id")->fetchAll();
$page_title='Subscription';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';
?><div class="page-head"><div><span class="eyebrow">CANDIDATE PLAN</span><h1>Subscription</h1><p class="muted">Choose a plan for premium portal features. Government application fees are never collected here.</p></div></div>
<?php if($message):?><div class="alert success"><?=e($message)?></div><?php endif;?><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="card"><h2>Current access</h2><?php if($sub):?><p><strong><?=e($sub['plan_name'])?></strong> · <?=e($sub['subscription_type'])?></p><p>Valid: <?=e($sub['start_date'])?> to <?=e($sub['end_date'])?></p><?php else:?><p>No active subscription.</p><?php endif;?></div>
<div class="cards"><?php foreach($plans as $plan):?><div class="card"><h2><?=e($plan['plan_name'])?></h2><p><strong>₹<?=number_format((float)$plan['price'],2)?></strong> · <?=e($plan['duration_days'])?> days</p><p><?=nl2br(e(str_replace(';',"\n",$plan['features']??'')))?></p><?php if((int)$plan['is_trial']===1):?><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="start_trial"><button class="btn btn-primary" <?= $sub?'disabled':'' ?>>Start Free Trial</button></form><?php else:?><button class="btn btn-light" disabled>Payment gateway — next configuration</button><?php endif;?></div><?php endforeach;?></div>
<div class="card"><h2>Payment policy</h2><p>Paid subscription payments will be processed through a configured payment gateway. Recruitment/application fees remain payable directly on the official government recruitment website.</p></div>
<?php require '../includes/footer.php';?>
