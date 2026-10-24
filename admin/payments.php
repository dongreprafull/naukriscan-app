<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_permission('subscriptions.manage'); $pdo = db(); $error = null;
$adminId = (int)($_SESSION['admin_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['_csrf'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $note = trim($_POST['admin_note'] ?? '');
        $st = $pdo->prepare("SELECT * FROM payments WHERE id=? LIMIT 1"); $st->execute([$id]); $pay = $st->fetch();
        if (!$pay) { $error = 'Payment record not found.'; }
        elseif ($pay['payment_status'] !== 'pending') { $error = 'This payment was already reviewed.'; }
        elseif ($action === 'approve') {
            $plan = null;
            if ($pay['plan_id']) { $ps = $pdo->prepare("SELECT * FROM subscription_plans WHERE id=?"); $ps->execute([$pay['plan_id']]); $plan = $ps->fetch(); }
            if (!$plan) { $error = 'Plan for this payment no longer exists.'; }
            else {
                $pdo->beginTransaction();
                try {
                    $start = new DateTimeImmutable('today');
                    $end = $start->modify('+' . max(0, (int)$plan['duration_days'] - 1) . ' days');
                    $ins = $pdo->prepare("INSERT INTO subscriptions(user_id,plan_id,start_date,end_date,status,auto_renew,subscription_type,created_at,activated_at) VALUES(?,?,?,?, 'active',0,'paid',NOW(),NOW())");
                    $ins->execute([$pay['user_id'], $plan['id'], $start->format('Y-m-d'), $end->format('Y-m-d')]);
                    $subId = (int)$pdo->lastInsertId();
                    $pdo->prepare("UPDATE payments SET payment_status='verified', subscription_id=?, reviewed_by=?, reviewed_at=NOW(), payment_date=NOW(), admin_note=? WHERE id=?")
                        ->execute([$subId, $adminId, $note, $id]);
                    $pdo->prepare("INSERT INTO audit_logs(admin_id,action,module,record_id,new_value,created_at) VALUES(?,?,?,?,?,NOW())")
                        ->execute([$adminId, 'PAYMENT_VERIFIED', 'payments', $id, 'Subscription #' . $subId . ' activated for user #' . $pay['user_id']]);
                    $pdo->commit();
                    flash('success', 'Payment verified — subscription activated.');
                } catch (Throwable $e) { $pdo->rollBack(); $error = 'Could not activate the subscription.'; }
            }
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE payments SET payment_status='rejected', reviewed_by=?, reviewed_at=NOW(), admin_note=? WHERE id=?")->execute([$adminId, $note, $id]);
            $pdo->prepare("INSERT INTO audit_logs(admin_id,action,module,record_id,new_value,created_at) VALUES(?,?,?,?,?,NOW())")->execute([$adminId, 'PAYMENT_REJECTED', 'payments', $id, $note]);
            flash('success', 'Payment marked as rejected.');
        } else { $error = 'Unknown action.'; }
        if (!$error) redirect('payments.php');
    }
}

$tab = ($_GET['tab'] ?? 'pending'); if (!in_array($tab, ['pending', 'verified', 'rejected'], true)) $tab = 'pending';
$st = $pdo->prepare("SELECT p.*, u.user_uid, u.email, u.mobile, pl.plan_name, pl.price AS plan_price, pl.duration_days
    FROM payments p JOIN users u ON u.id=p.user_id LEFT JOIN subscription_plans pl ON pl.id=p.plan_id
    WHERE p.payment_status=? ORDER BY p.created_at DESC");
$st->execute([$tab]); $rows = $st->fetchAll();
$page_title = 'Payments'; $asset_prefix = '../'; $home_link = '../index.php'; require '../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">ADMIN</span><h1>Payments</h1><p class="muted">Review manually-submitted UPI/QR payments and activate subscriptions.</p></div><a class="btn btn-light" href="payment-settings.php">QR / UPI Settings</a></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="queue-tabs">
<a class="<?= $tab === 'pending' ? 'active' : '' ?>" href="?tab=pending">Pending</a>
<a class="<?= $tab === 'verified' ? 'active' : '' ?>" href="?tab=verified">Verified</a>
<a class="<?= $tab === 'rejected' ? 'active' : '' ?>" href="?tab=rejected">Rejected</a>
</div>
<div class="card table-wrap">
<table><thead><tr><th>Candidate</th><th>Plan</th><th>Amount</th><th>Transaction ID</th><th>Receipt</th><th>Submitted</th><?= $tab === 'pending' ? '<th>Action</th>' : '<th>Reviewed</th>' ?></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= e($r['user_uid']) ?><br><small class="muted"><?= e($r['email']) ?></small></td>
<td><?= e($r['plan_name'] ?: '—') ?> <?php if($r['duration_days']):?><small class="muted">(<?= e($r['duration_days']) ?>d)</small><?php endif; ?></td>
<td>₹<?= number_format((float)$r['amount'], 2) ?></td>
<td><?= e($r['transaction_id'] ?: '—') ?></td>
<td><?php if ($r['receipt_file_path']): ?><a href="view-receipt.php?id=<?= e($r['id']) ?>" target="_blank">View</a><?php else: ?>—<?php endif; ?></td>
<td><?= e($r['created_at']) ?></td>
<?php if ($tab === 'pending'): ?>
<td class="action-row">
<form method="post" class="inline"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-small btn-primary" onclick="return confirm('Activate this subscription?')">Approve</button></form>
<form method="post" class="inline"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= e($r['id']) ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-small btn-light" onclick="return confirm('Reject this payment?')">Reject</button></form>
</td>
<?php else: ?>
<td><?= e($r['admin_note'] ?: '—') ?><br><small class="muted"><?= e($r['reviewed_at']) ?></small></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php if (!$rows): ?><div class="empty">No <?= e($tab) ?> payments.</div><?php endif; ?>
</div>
<?php require '../includes/footer.php'; ?>
