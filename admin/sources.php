<?php
require_once '../config/app.php'; require_once '../includes/security.php';
start_secure_session(); require_permission('sources.view'); $pdo=db(); $error=null;

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_check($_POST['_csrf']??'')) $error='Invalid request.';
    else try {
        $action=$_POST['action']??'';
        if ($action==='save') {
            require_permission('sources.manage');
            $id=(int)($_POST['id']??0);
            $url=trim($_POST['source_url']??'');
            if (!filter_var($url,FILTER_VALIDATE_URL) || !preg_match('~^https?://~i',$url)) throw new RuntimeException('Enter a valid HTTP/HTTPS official URL.');
            $freq=max(60,min(10080,(int)($_POST['scan_frequency_minutes']??360)));
            $data=[trim($_POST['source_name']??''),trim($_POST['organization']??''),trim($_POST['category']??'Government'),trim($_POST['state']??''),$url,trim($_POST['source_type']??'Recruitment Website'),$freq,in_array($_POST['priority']??'', ['high','medium','low'],true)?$_POST['priority']:'medium',in_array($_POST['parser_mode']??'', ['auto','pdf','html'],true)?$_POST['parser_mode']:'auto',(int)!empty($_POST['active'])];
            if ($data[0]==='') throw new RuntimeException('Source name is required.');
            if ($id) {
                $st=$pdo->prepare("UPDATE job_sources SET source_name=?,organization=?,category=?,state=?,source_url=?,source_type=?,scan_frequency_minutes=?,priority=?,parser_mode=?,active=?,updated_at=NOW() WHERE id=?");
                $data[]=$id; $st->execute($data); audit_admin('UPDATE','job_sources',$id,null,json_encode(['source_name'=>$data[0],'source_url'=>$url])); flash('success','Government source updated.');
            } else {
                $st=$pdo->prepare("INSERT INTO job_sources(source_name,organization,category,state,source_url,source_type,scan_frequency_minutes,priority,parser_mode,active,created_at,created_by,next_scan_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW(),?,NOW())");
                $data[]=$_SESSION['admin_id']; $st->execute($data); $newId=(int)$pdo->lastInsertId(); audit_admin('CREATE','job_sources',$newId,null,json_encode(['source_name'=>$data[0],'source_url'=>$url])); flash('success','Government source added. It is now part of the AI scan queue.');
            }
            redirect('sources.php');
        }
        if ($action==='toggle') {
            require_permission('sources.manage'); $id=(int)$_POST['id']; $active=(int)!empty($_POST['active']);
            $pdo->prepare('UPDATE job_sources SET active=?,updated_at=NOW(),next_scan_at=CASE WHEN ?=1 THEN NOW() ELSE NULL END WHERE id=?')->execute([$active,$active,$id]); audit_admin('TOGGLE','job_sources',$id,null,(string)$active); flash('success',$active?'Source enabled.':'Source paused.'); redirect('sources.php');
        }
        if ($action==='scan') {
            require_permission('sources.scan'); $id=(int)$_POST['id'];
            $pdo->prepare('UPDATE job_sources SET next_scan_at=NOW(),last_status=\'queued\',updated_at=NOW() WHERE id=?')->execute([$id]); audit_admin('QUEUE_SCAN','job_sources',$id); flash('success','Source queued for the next scanner run.'); redirect('sources.php');
        }
    } catch(Throwable $e) { $error=$e->getMessage(); }
}

$edit=null; if(isset($_GET['edit'])) { $st=$pdo->prepare('SELECT * FROM job_sources WHERE id=?'); $st->execute([(int)$_GET['edit']]); $edit=$st->fetch(); }
$rows=$pdo->query("SELECT s.*, (SELECT COUNT(*) FROM source_scan_logs l WHERE l.source_id=s.id) scan_count, (SELECT COUNT(*) FROM source_documents d WHERE d.source_id=s.id) document_count FROM job_sources s ORDER BY s.priority='high' DESC,s.active DESC,s.created_at DESC")->fetchAll();
$stats=['active'=>0,'healthy'=>0,'review'=>0,'failed'=>0]; foreach($rows as $r){ if((int)$r['active'])$stats['active']++; if(in_array($r['last_status'],['success','completed'],true))$stats['healthy']++; if($r['last_status']==='review')$stats['review']++; if(in_array($r['last_status'],['failed','error'],true))$stats['failed']++; }
$page_title='Government Sources'; $asset_prefix='../'; $home_link='../index.php'; require '../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">AI JOB DISCOVERY</span><h1>Government Sources</h1><p class="muted">Add official government recruitment websites manually. Active sources are automatically picked up by the scanner.</p></div><?php if(admin_has_permission('sources.manage')):?><a class="btn btn-primary" href="#source-form">+ Add Source</a><?php endif;?></div>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<div class="stats stats-modern"><div><span>Active Sources</span><b><?=e($stats['active'])?></b></div><div><span>Healthy</span><b><?=e($stats['healthy'])?></b></div><div><span>Review</span><b><?=e($stats['review'])?></b></div><div><span>Failed</span><b><?=e($stats['failed'])?></b></div></div>
<?php if(admin_has_permission('sources.manage')): ?><div class="card source-form" id="source-form"><div class="card-head"><div><h2><?= $edit?'Edit Government Source':'Add Government Source'?></h2><p class="muted">Use only official recruitment/career/notification URLs.</p></div></div><form class="form-grid" method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($edit['id']??0)?>">
<label>Source Name*<input name="source_name" required value="<?=e($edit['source_name']??'')?>" placeholder="UPSC / SSC / MPSC"></label>
<label>Organization<input name="organization" value="<?=e($edit['organization']??'')?>" placeholder="Union Public Service Commission"></label>
<label>Category<select name="category"><option>Government</option><option>State Government</option><option>PSU</option><option>Banking</option><option>Railway</option><option>Defence</option><option>University</option><option>Other</option></select></label>
<label>State<input name="state" value="<?=e($edit['state']??'')?>" placeholder="All India / Maharashtra"></label>
<label class="span-2">Official Website / Recruitment URL*<input type="url" name="source_url" required value="<?=e($edit['source_url']??'')?>" placeholder="https://example.gov.in/recruitment"></label>
<label>Source Type<input name="source_type" value="<?=e($edit['source_type']??'Recruitment Website')?>" placeholder="Recruitment Website / PDF / Careers"></label>
<label>Scan Frequency<select name="scan_frequency_minutes"><option value="60">Every 1 hour</option><option value="360" selected>Every 6 hours</option><option value="720">Every 12 hours</option><option value="1440">Daily</option><option value="10080">Weekly</option></select></label>
<label>Priority<select name="priority"><option value="high">High</option><option value="medium" selected>Medium</option><option value="low">Low</option></select></label>
<label>Parser<select name="parser_mode"><option value="auto">Auto detect</option><option value="pdf">PDF focused</option><option value="html">HTML focused</option></select></label>
<label class="inline"><input type="checkbox" name="active" value="1" <?=!isset($edit)||!empty($edit['active'])?'checked':''?>> Active source</label>
<div class="form-actions"><button class="btn btn-primary">Save Source</button><?php if($edit):?><a class="btn btn-light" href="sources.php">Cancel</a><?php endif;?></div></form></div><?php endif;?>
<div class="card"><div class="card-head"><div><h2>Source Registry</h2><p class="muted">AI scans these sources according to their schedule.</p></div><span class="tag">Scanner automation</span></div><div class="table-wrap"><table><tr><th>Source</th><th>Category</th><th>Schedule</th><th>Last Scan</th><th>Found</th><th>Status</th><th>Actions</th></tr><?php foreach($rows as $s):?><tr><td><b><?=e($s['source_name'])?></b><br><small><?=e($s['organization']?:'—')?></small></td><td><?=e($s['category'])?><br><small><?=e($s['state']?:'All India')?></small></td><td><?=e((int)$s['scan_frequency_minutes']/60)?>h</td><td><?=e($s['last_scan_at']?:'Never')?></td><td><?=e($s['last_found_count'])?> / <?=e($s['last_new_count'])?></td><td><span class="status-dot <?=in_array($s['last_status'],['success','completed'],true)?'ok':($s['last_status']==='failed'||$s['last_status']==='error'?'bad':'warn')?>"></span><?=e($s['last_status'])?></td><td><div class="action-row"><?php if(admin_has_permission('sources.manage')):?><a class="btn btn-small btn-light" href="sources.php?edit=<?=e($s['id'])?>">Edit</a><?php endif;?><?php if(admin_has_permission('sources.scan')):?><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="scan"><input type="hidden" name="id" value="<?=e($s['id'])?>"><button class="btn btn-small btn-light">Scan Now</button></form><?php endif;?><?php if(admin_has_permission('sources.manage')):?><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?=e($s['id'])?>"><input type="hidden" name="active" value="<?=empty($s['active'])?1:0?>"><button class="btn btn-small"><?=empty($s['active'])?'Enable':'Pause'?></button></form><?php endif;?></div></td></tr><?php endforeach;?></table></div></div>
<?php require '../includes/footer.php'; ?>
