<?php
require_once '../config/app.php'; require_once '../includes/security.php'; start_secure_session(); require_user();
$pdo=db(); $uid=(int)$_SESSION['user_id']; $error=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_check($_POST['_csrf']??'')){ $error='Invalid request.'; }
 else {
  try {
   $pdo->beginTransaction();
   $sql="INSERT INTO user_profiles
   (user_id,first_name,middle_name,last_name,father_name,mother_name,date_of_birth,gender,nationality,category,sub_category,pwd_status,pwd_percentage,ex_serviceman,domicile_state)
   VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
   ON DUPLICATE KEY UPDATE
   first_name=VALUES(first_name),middle_name=VALUES(middle_name),last_name=VALUES(last_name),
   father_name=VALUES(father_name),mother_name=VALUES(mother_name),date_of_birth=VALUES(date_of_birth),
   gender=VALUES(gender),nationality=VALUES(nationality),category=VALUES(category),sub_category=VALUES(sub_category),
   pwd_status=VALUES(pwd_status),pwd_percentage=VALUES(pwd_percentage),ex_serviceman=VALUES(ex_serviceman),
   domicile_state=VALUES(domicile_state)";
   $pdo->prepare($sql)->execute([
    $uid,trim($_POST['first_name']??''),trim($_POST['middle_name']??''),trim($_POST['last_name']??''),
    trim($_POST['father_name']??''),trim($_POST['mother_name']??''),($_POST['date_of_birth']??'')?:null,
    trim($_POST['gender']??''),trim($_POST['nationality']??'Indian'),trim($_POST['category']??''),
    trim($_POST['sub_category']??''),(int)($_POST['pwd_status']??0),
    ($_POST['pwd_percentage']??'')!==''?(float)$_POST['pwd_percentage']:null,
    (int)($_POST['ex_serviceman']??0),trim($_POST['domicile_state']??'')
   ]);

   $addressTypes=['current','permanent'];
   foreach($addressTypes as $type){
    $pdo->prepare("INSERT INTO user_addresses
      (user_id,address_type,address_line_1,address_line_2,village,city,district,state,pincode,country)
      VALUES(?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE address_line_1=VALUES(address_line_1),address_line_2=VALUES(address_line_2),
      village=VALUES(village),city=VALUES(city),district=VALUES(district),state=VALUES(state),
      pincode=VALUES(pincode),country=VALUES(country)")
      ->execute([$uid,$type,trim($_POST[$type.'_address1']??''),trim($_POST[$type.'_address2']??''),
        trim($_POST[$type.'_village']??''),trim($_POST[$type.'_city']??''),trim($_POST[$type.'_district']??''),
        trim($_POST[$type.'_state']??''),trim($_POST[$type.'_pincode']??''),trim($_POST[$type.'_country']??'India')]);
   }

   $fields=[
    'first_name','father_name','date_of_birth','gender','category','nationality',
    'domicile_state','current_address1','current_city','current_district','current_state','current_pincode'
   ];
   $filled=0;
   foreach($fields as $f) if(trim($_POST[$f]??'')!=='') $filled++;
   $completion=min(100,(int)round($filled/count($fields)*100));
   $pdo->prepare("UPDATE user_profiles SET profile_completion=? WHERE user_id=?")->execute([$completion,$uid]);
   $pdo->commit();
   flash('success','Profile and address saved successfully.');
   redirect('profile.php');
  } catch(Throwable $e) {
   if($pdo->inTransaction()) $pdo->rollBack();
   $error='Could not save profile. Please check the database structure.';
  }
 }
}

$st=$pdo->prepare("SELECT * FROM user_profiles WHERE user_id=?");$st->execute([$uid]);$p=$st->fetch()?:[];
$addresses=[];$st=$pdo->prepare("SELECT * FROM user_addresses WHERE user_id=?");$st->execute([$uid]);foreach($st as $a)$addresses[$a['address_type']]=$a;
$page_title='My Profile';$asset_prefix='../';$home_link='../index.php';require '../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">MASTER PROFILE</span><h1>My Profile</h1><p class="muted">Complete this once so the matching engine can use accurate eligibility information.</p></div><div class="tag">Completion: <?=e($p['profile_completion']??0)?>%</div></div>
<?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?>
<form class="card" method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>">
<h2>1. Personal Details</h2><div class="form-grid">
<label>First Name<input name="first_name" value="<?=e($p['first_name']??'')?>" required></label>
<label>Middle Name<input name="middle_name" value="<?=e($p['middle_name']??'')?>"></label>
<label>Last Name<input name="last_name" value="<?=e($p['last_name']??'')?>"></label>
<label>Father's Name<input name="father_name" value="<?=e($p['father_name']??'')?>"></label>
<label>Mother's Name<input name="mother_name" value="<?=e($p['mother_name']??'')?>"></label>
<label>Date of Birth<input type="date" name="date_of_birth" value="<?=e($p['date_of_birth']??'')?>"></label>
<label>Gender<select name="gender"><option value="">Select</option><?php foreach(['Male','Female','Other'] as $v):?><option value="<?=e($v)?>" <?=($p['gender']??'')===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label>
<label>Nationality<input name="nationality" value="<?=e($p['nationality']??'Indian')?>"></label>
<label>Category<select name="category"><option value="">Select</option><?php foreach(['General','OBC','SC','ST','EWS','Other'] as $v):?><option value="<?=e($v)?>" <?=($p['category']??'')===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></label>
<label>Sub Category / Certificate Type<input name="sub_category" value="<?=e($p['sub_category']??'')?>"></label>
<label>PwD Status<select name="pwd_status"><option value="0" <?=empty($p['pwd_status'])?'selected':''?>>No</option><option value="1" <?=!empty($p['pwd_status'])?'selected':''?>>Yes</option></select></label>
<label>PwD Percentage<input type="number" step="0.01" min="0" max="100" name="pwd_percentage" value="<?=e($p['pwd_percentage']??'')?>"></label>
<label>Ex-Serviceman<select name="ex_serviceman"><option value="0" <?=empty($p['ex_serviceman'])?'selected':''?>>No</option><option value="1" <?=!empty($p['ex_serviceman'])?'selected':''?>>Yes</option></select></label>
<label>Domicile State<input name="domicile_state" value="<?=e($p['domicile_state']??'')?>"></label>
</div>
<h2 class="section-title">2. Current Address</h2><div class="form-grid">
<label>Address Line 1<input name="current_address1" value="<?=e($addresses['current']['address_line_1']??'')?>"></label>
<label>Address Line 2<input name="current_address2" value="<?=e($addresses['current']['address_line_2']??'')?>"></label>
<label>Village / Area<input name="current_village" value="<?=e($addresses['current']['village']??'')?>"></label>
<label>City / Town<input name="current_city" value="<?=e($addresses['current']['city']??'')?>"></label>
<label>District<input name="current_district" value="<?=e($addresses['current']['district']??'')?>"></label>
<label>State<input name="current_state" value="<?=e($addresses['current']['state']??'')?>"></label>
<label>PIN Code<input name="current_pincode" maxlength="10" value="<?=e($addresses['current']['pincode']??'')?>"></label>
<label>Country<input name="current_country" value="<?=e($addresses['current']['country']??'India')?>"></label>
</div>
<h2 class="section-title">3. Permanent Address</h2><div class="form-grid">
<label>Address Line 1<input name="permanent_address1" value="<?=e($addresses['permanent']['address_line_1']??'')?>"></label>
<label>Address Line 2<input name="permanent_address2" value="<?=e($addresses['permanent']['address_line_2']??'')?>"></label>
<label>Village / Area<input name="permanent_village" value="<?=e($addresses['permanent']['village']??'')?>"></label>
<label>City / Town<input name="permanent_city" value="<?=e($addresses['permanent']['city']??'')?>"></label>
<label>District<input name="permanent_district" value="<?=e($addresses['permanent']['district']??'')?>"></label>
<label>State<input name="permanent_state" value="<?=e($addresses['permanent']['state']??'')?>"></label>
<label>PIN Code<input name="permanent_pincode" maxlength="10" value="<?=e($addresses['permanent']['pincode']??'')?>"></label>
<label>Country<input name="permanent_country" value="<?=e($addresses['permanent']['country']??'India')?>"></label>
</div>
<div class="form-actions"><button class="btn btn-primary">Save Profile</button><a class="btn btn-light" href="education.php">Next: Education →</a></div>
</form>
<?php require '../includes/footer.php'; ?>