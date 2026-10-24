<?php
require_once '../config/app.php'; require_once '../includes/security.php';
start_secure_session();
require_permission('users.view');
$pdo=db();
$q=trim($_GET['q']??'');
$where=''; $params=[];
if($q!==''){
    $where="WHERE u.email LIKE ? OR u.mobile LIKE ? OR CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,'')) LIKE ?";
    $params=["%$q%","%$q%","%$q%"];
}
$st=$pdo->prepare("SELECT u.id,u.user_uid,u.email,u.mobile,u.account_status,u.created_at,p.first_name,p.last_name,p.category,p.domicile_state FROM users u LEFT JOIN user_profiles p ON p.user_id=u.id $where ORDER BY u.created_at DESC");
$st->execute($params); $rows=$st->fetchAll();
$page_title='Users'; $asset_prefix='../'; $home_link='../index.php'; require '../includes/header.php';
?>
<div class="page-head"><div><h1>Users</h1><p class="muted">Search and manage candidate accounts.</p></div><a class="btn btn-light" href="export-users.php">Export Excel/CSV</a></div>
<form class="searchbar"><input name="q" value="<?=e($q)?>" placeholder="Search name, email or mobile"><button class="btn btn-primary">Search</button></form>
<div class="card table-wrap"><table><tr><th>UID</th><th>Name</th><th>Mobile</th><th>Email</th><th>Category</th><th>Status</th><th>Action</th></tr>
<?php foreach($rows as $r): ?><tr><td><?=e($r['user_uid'])?></td><td><?=e(trim($r['first_name'].' '.$r['last_name']))?></td><td><?=e($r['mobile'])?></td><td><?=e($r['email'])?></td><td><?=e($r['category'])?></td><td><span class="tag"><?=e($r['account_status'])?></span></td><td><a href="user-view.php?id=<?=e($r['id'])?>">View/Edit</a></td></tr><?php endforeach; ?>
</table></div>
<?php require '../includes/footer.php';?>
