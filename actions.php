<?php
require __DIR__.'/includes/bootstrap.php';
if(!$user)redirect('login.php');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('POST required');}
$target='index.php';$transaction=false;$uploaded=null;
try{
 check_csrf();$action=field('action',40);$overrideRequested=$action==='override_approve';
 if($overrideRequested){require_role('Administrator');$action='approve';}
 if($action==='review_registration'||($action==='save_record'&&($_POST['entity']??'')==='users')){require_role('Administrator');auth_limit('account-review',(string)$user['id'],5,900);if(!password_verify(auth_input('confirmation_password',200),$user['password_hash']))throw new RuntimeException('Confirm your administrator password to manage accounts.');auth_clear_limit('account-review',(string)$user['id']);}
 if($action==='logout'){$_SESSION=[];session_destroy();redirect('login.php');}
 if(str_starts_with($action,'factor_')){require __DIR__.'/includes/profile-auth-actions.php';}
 lock_transaction();$transaction=true;
 if($action==='save_request'){
  $id=(int)($_POST['id']??0);$old=$id?get_request($id):null;
  if($old){lock_record('requisitions',$id);$old=get_request($id);if((int)$old['requester_id']!==(int)$user['id']||!in_array($old['status'],['Draft','Returned for Correction']))throw new RuntimeException('Only your drafts and returned requests can be edited.');}
  $start=datetime_value('start_datetime');$end=datetime_value('end_datetime');if($end<=$start)throw new RuntimeException('Estimated return must be after departure.');
  $office=(int)$user['office_id'];if(!one("SELECT id FROM offices WHERE id=? AND status='Active'",[$office]))throw new RuntimeException('Your office is inactive. Contact your administrator.');$type=field('vehicle_type',40);if(!in_array($type,['Van','MPV','SUV','Pickup','Sedan','Bus']))throw new RuntimeException('Invalid vehicle type.');
  $fuel=filter_var($_POST['fuel_quantity']??0,FILTER_VALIDATE_FLOAT);if($fuel===false||$fuel<0||$fuel>10000)throw new RuntimeException('Enter a valid fuel quantity.');
  $status=($_POST['submit_mode']??'')==='draft'?'Draft':'Pending Administrative Approval';
  $values=[$office,$type,field('preferred_driver',160,false),field('passengers',5000),$start,$end,field('destination',255),field('purpose',5000),isset($_POST['fuel_allocation'])?1:0,$fuel,field('fuel_remarks',1000,false),$status];
  if($id){run('UPDATE requisitions SET office_id=?,vehicle_type=?,preferred_driver=?,passengers=?,start_datetime=?,end_datetime=?,destination=?,purpose=?,fuel_allocation=?,fuel_quantity=?,fuel_remarks=?,status=? WHERE id=?',[...$values,$id]);}
  else{run('INSERT INTO requisitions(office_id,vehicle_type,preferred_driver,passengers,start_datetime,end_datetime,destination,purpose,fuel_allocation,fuel_quantity,fuel_remarks,status,requester_id,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[...$values,$user['id'],date('Y-m-d H:i:s')]);$id=(int)$pdo->lastInsertId();run('UPDATE requisitions SET reference=? WHERE id=?',['VR-'.date('Y').'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),$id]);}
  if($status==='Pending Administrative Approval')foreach(all("SELECT id FROM users WHERE status='Active' AND role='Administrator'") as $reviewer)run('INSERT INTO notifications(user_id,message,requisition_id,created_at) VALUES(?,?,?,?)',[$reviewer['id'],'A requisition is awaiting administrator approval.',$id,date('Y-m-d H:i:s')]);
  if(($_POST['return_to']??'')==='index.php?page=assistant')unset($_SESSION['booking_chat']);
  audit('Requisition saved','Request #'.$id.' · '.$status);$target='index.php?page=request&id='.$id;flash($status==='Draft'?'Draft saved.':'Requisition submitted for administrator approval.');
 }elseif(in_array($action,['approve','reject','return_correction','cancel','dispatch','receive','complete'])){
  $id=number('id',1);lock_record('requisitions',$id);$r=get_request($id);$target='index.php?page=request&id='.$id;
  $remark=field('remarks',2000,false);$new='';
  if($action==='cancel'){
   if((int)$r['requester_id']!==(int)$user['id']||!in_array($r['status'],['Draft','Pending Supervisor','Returned for Correction','Pending Administrative Approval','Approved']))throw new RuntimeException('This request cannot be cancelled.');$new='Cancelled';
  }elseif(in_array($action,['approve','reject','return_correction'])){
   require_role('Administrator');
   if(!in_array($r['status'],['Pending Supervisor','Pending Administrative Approval']))throw new RuntimeException('This request is no longer awaiting approval.');
   if(!password_verify((string)($_POST['password']??''),$user['password_hash']))throw new RuntimeException('The approval confirmation password is incorrect.');
   if($action!=='approve'&&$remark==='')throw new RuntimeException('Add a reason for returning or rejecting this request.');
   $new=$action==='reject'?'Rejected':($action==='return_correction'?'Returned for Correction':'Approved');
   if($new==='Approved'){
    $vehicle=number('vehicle_id',1);$driver=number('driver_id',1);$v=lock_record('vehicles',$vehicle);$d=lock_record('drivers',$driver);
    if(!$v||!$d)throw new RuntimeException('Select a valid vehicle and driver.');
    $blockingIssues=[...vehicle_assignment_issues($v,$r),...driver_assignment_issues($d,$r)];
    if($blockingIssues)throw new RuntimeException(implode(' ',$blockingIssues));
    $issues=conflicts($vehicle,$driver,$r['start_datetime'],$r['end_datetime'],$id);
    $override=$overrideRequested?field('override_reason',1000):'';
    if($overrideRequested&&mb_strlen($override)<15)throw new RuntimeException('Enter an override reason of at least 15 characters.');
    if($issues){
     if(!$overrideRequested)throw new RuntimeException(implode(' ',$issues).' This schedule is blocked. Choose another assignment or use the administrator Override & approve button.');
     audit('Schedule conflict overridden',$r['reference'].' · '.implode(' ',$issues).' · '.$override);$remark.=' [Override: '.$override.']';
    }
    run('UPDATE requisitions SET vehicle_id=?,driver_id=? WHERE id=?',[$vehicle,$driver,$id]);
   }
   run('INSERT INTO approvals(requisition_id,user_id,stage,decision,remarks,created_at) VALUES(?,?,?,?,?,?)',[$id,$user['id'],'Administrative',$action==='approve'?'Approved':$new,$remark,date('Y-m-d H:i:s')]);
  }elseif($action==='dispatch'){
   require_role('Dispatcher','Administrator');if($r['status']!=='Approved')throw new RuntimeException('Only approved requests can be dispatched.');
   $v=lock_record('vehicles',(int)$r['vehicle_id']);lock_record('drivers',(int)$r['driver_id']);
   if(!in_array($v['status'],['Available','Reserved'])||one("SELECT id FROM requisitions WHERE status='Dispatched' AND (vehicle_id=? OR driver_id=?)",[$r['vehicle_id'],$r['driver_id']]))throw new RuntimeException('Vehicle or driver is currently unavailable.');
   $out=number('odometer_out');$departure=datetime_value('actual_time');if($departure>date('Y-m-d H:i:s'))throw new RuntimeException('Actual departure cannot be in the future.');if($out<(int)$v['odometer'])throw new RuntimeException('Odometer out cannot be below the current reading.');
   if(one('SELECT id FROM vehicle_blocks WHERE vehicle_id=? AND start_datetime<=? AND end_datetime>?',[$v['id'],$departure,$departure]))throw new RuntimeException('Vehicle is blocked for maintenance.');
   run('INSERT INTO vehicle_movements(requisition_id,departure,odometer_out,condition_text,remarks,checked_by) VALUES(?,?,?,?,?,?)',[$id,$departure,$out,field('condition_text',160),$remark,$user['id']]);run('UPDATE vehicles SET odometer=? WHERE id=?',[$out,$v['id']]);$new='Dispatched';
  }elseif($action==='receive'){
   require_role('Dispatcher','Administrator');if($r['status']!=='Dispatched')throw new RuntimeException('Only dispatched vehicles can be received.');lock_record('vehicles',(int)$r['vehicle_id']);
   $m=one('SELECT * FROM vehicle_movements WHERE requisition_id=?',[$id]);$in=number('odometer_in');$time=datetime_value('actual_time');if($in<(int)$m['odometer_out']||$time<$m['departure']||$time>date('Y-m-d H:i:s'))throw new RuntimeException('Check the return time and odometer. Return must be after departure and cannot be in the future.');
   $condition=field('condition_text',160);run('UPDATE vehicle_movements SET return_time=?,odometer_in=?,condition_text=?,remarks=?,checked_by=? WHERE requisition_id=?',[$time,$in,$condition,$remark,$user['id'],$id]);run('UPDATE vehicles SET odometer=?,condition_text=? WHERE id=?',[$in,$condition,$r['vehicle_id']]);$new='Returned';
  }elseif($action==='complete'){require_role('Dispatcher','Administrator');if($r['status']!=='Returned')throw new RuntimeException('Receive the vehicle before completing the trip.');$new='Completed';}
  run('UPDATE requisitions SET status=? WHERE id=?',[$new,$id]);audit('Request '.$new,$r['reference'].' · '.$remark);notify_request($r,$new);flash('Request marked '.strtolower($new).'.');
 }elseif($action==='review_registration'){
  require_role('Administrator');$id=number('id',1);$decision=field('decision',20);
  if(!in_array($decision,['approve','reject']))throw new RuntimeException('Choose approve or reject.');
  $pending=one('SELECT * FROM users WHERE id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id]);
  if(!$pending||$pending['status']!=='Pending')throw new RuntimeException('This account request is no longer pending.');
  if($decision==='approve'&&!one("SELECT id FROM offices WHERE id=? AND status='Active'",[$pending['office_id']]))throw new RuntimeException('The requested office is inactive. Update the office before approval.');
  run('UPDATE users SET status=?,role=? WHERE id=?',[$decision==='approve'?'Active':'Inactive','Requester',$id]);
  audit('Registration '.($decision==='approve'?'approved':'rejected'),'Account #'.$id);flash($decision==='approve'?'Account approved. The requester can now sign in and enroll an authenticator.':'Account request rejected.');$target='index.php?page=users';
 }elseif($action==='save_record'){
  $entity=field('entity',30);if(in_array($entity,['users','offices']))require_role('Administrator');else require_role('Administrator','Administrative Officer');
  $schemas=record_schemas();if(!isset($schemas[$entity]))throw new RuntimeException('Invalid record type.');$values=[];
  foreach($schemas[$entity]['fields'] as $key=>$f){if($key==='password')continue;$value=field($key,255,!($f['optional']??false));if(($f['type']??'')==='number'&&(!ctype_digit($value)||(int)$value<($f['min']??0)))throw new RuntimeException('Invalid '.$f['label']);if(isset($f['options'])&&!array_key_exists($value,$f['options']))throw new RuntimeException('Invalid '.$f['label']);if(($f['type']??'')==='date'&&(!DateTime::createFromFormat('!Y-m-d',$value)||DateTime::createFromFormat('!Y-m-d',$value)->format('Y-m-d')!==$value))throw new RuntimeException('Invalid '.$f['label']);$values[$key]=$value;}
  $id=(int)($_POST['id']??0);if($id&&!one("SELECT id FROM $entity WHERE id=?",[$id]))throw new RuntimeException('Record not found.');
  if($entity==='users'){if($id&&$values['status']!=='Pending'&&(one('SELECT status FROM users WHERE id=?',[$id])['status']??'')==='Pending')throw new RuntimeException('Approve or reject pending registrations from the account requests panel.');if(!filter_var($values['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email.');$pass=field('password',200,false);if(!$id||$pass!==''){auth_password_policy($pass);$values['password_hash']=password_hash($pass,PASSWORD_DEFAULT);}if($id===$user['id']&&($values['role']!=='Administrator'||$values['status']!=='Active'))throw new RuntimeException('You cannot deactivate or demote your own administrator account.');}
  if(in_array($entity,['vehicles','drivers'])&&$id){lock_record($entity,$id);$col=$entity==='vehicles'?'vehicle_id':'driver_id';if($values['status']!=='Available'&&one("SELECT id FROM requisitions WHERE $col=? AND status IN ('Approved','Dispatched') AND end_datetime>?",[$id,date('Y-m-d H:i:s')]))throw new RuntimeException('Resolve upcoming assignments before making this resource unavailable.');}
  if($entity==='vehicles'&&isset($_FILES['photo'])&&$_FILES['photo']['error']!==UPLOAD_ERR_NO_FILE){
   $upload=$_FILES['photo'];if($upload['error']!==UPLOAD_ERR_OK||$upload['size']>4*1024*1024||!is_uploaded_file($upload['tmp_name']))throw new RuntimeException('Upload a JPEG or PNG photograph up to 4 MB.');
   $info=getimagesize($upload['tmp_name']);if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG])||$info[0]*$info[1]>12000000)throw new RuntimeException('Invalid image or image exceeds 12 megapixels.');
   $img=$info[2]===IMAGETYPE_JPEG?imagecreatefromjpeg($upload['tmp_name']):imagecreatefrompng($upload['tmp_name']);if(!$img)throw new RuntimeException('The image could not be decoded.');
   $path='assets/uploads/'.bin2hex(random_bytes(16)).'.jpg';$uploaded=__DIR__.'/'.$path;if(!imagejpeg($img,$uploaded,88))throw new RuntimeException('Unable to save the photograph.');imagedestroy($img);$values['photo_url']=$path;
  }
  if($id){$sets=implode(',',array_map(fn($k)=>$k.'=?',array_keys($values)));run("UPDATE $entity SET $sets WHERE id=?",[...array_values($values),$id]);}else{run("INSERT INTO $entity (".implode(',',array_keys($values)).') VALUES('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));}
  audit('Record saved',$entity.' #'.($id?:$pdo->lastInsertId()));flash('Record saved.');$target='index.php?page='.$entity;
 }elseif($action==='maintenance'){
  require_role('Administrator','Administrative Officer');$v=number('vehicle_id',1);if(!lock_record('vehicles',$v))throw new RuntimeException('Vehicle not found.');$start=datetime_value('start_datetime');$end=datetime_value('end_datetime');if($end<=$start)throw new RuntimeException('End must be after start.');if(conflicts($v,0,$start,$end))throw new RuntimeException('This maintenance block overlaps an assignment or another block.');run('INSERT INTO vehicle_blocks(vehicle_id,start_datetime,end_datetime,reason,created_by) VALUES(?,?,?,?,?)',[$v,$start,$end,field('reason',1000),$user['id']]);audit('Maintenance scheduled','Vehicle #'.$v);$target='index.php?page=maintenance';flash('Maintenance block added.');
 }elseif($action==='delete_block'){
  require_role('Administrator','Administrative Officer');$id=number('id',1);run('DELETE FROM vehicle_blocks WHERE id=?',[$id]);audit('Maintenance block removed','Block #'.$id);$target='index.php?page=maintenance';flash('Maintenance block removed.');
 }elseif($action==='settings'){
  require_role('Administrator');$minutes=number('turnaround_minutes');if($minutes>1440)throw new RuntimeException('Turnaround must be between 0 and 1440 minutes.');foreach(['organization','supervisor_signatory','admin_signatory','turnaround_minutes'] as $key)run('UPDATE system_settings SET setting_value=? WHERE setting_key=?',[field($key),$key]);audit('Settings updated');$target='index.php?page=settings';flash('Settings updated.');
 }elseif($action==='read_notifications'){run('UPDATE notifications SET is_read=1 WHERE user_id=?',[$user['id']]);$target='index.php?page=notifications';flash('Notifications marked as read.');}
 else throw new RuntimeException('Unknown action.');
 finish_transaction(true);$transaction=false;
}catch(Throwable $e){if($uploaded&&is_file($uploaded))unlink($uploaded);if($transaction)finish_transaction(false);flash($e instanceof PDOException?'This record could not be saved. Check for duplicate identifiers and valid linked records.':$e->getMessage(),'error');$_SESSION['old_input']=array_diff_key($_POST,array_flip(['password','confirmation_password','csrf','code']));$back=$_POST['return_to']??'';if(is_string($back)&&preg_match('/^index\.php(?:\?[a-zA-Z0-9_=&%-]*)?$/D',$back))$target=$back;}
redirect($target);
