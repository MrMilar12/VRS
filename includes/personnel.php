<?php
require_once __DIR__."/personnel-registry.php";
function personnel_schema(PDO $db): void {
 $mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
 $db->exec(file_get_contents(__DIR__.'/../database/personnel.'.($mysql?'mysql':'sqlite').'.sql'));
 $columns=$db->query($mysql?'SHOW COLUMNS FROM personnel_bookings':'PRAGMA table_info(personnel_bookings)')->fetchAll(PDO::FETCH_ASSOC);
 if(!in_array('preferred_personnel',array_column($columns,$mysql?'Field':'name'),true)){
  try{$db->exec("ALTER TABLE personnel_bookings ADD COLUMN preferred_personnel VARCHAR(160) NOT NULL DEFAULT ''");}
  catch(PDOException $e){
   // Another startup request may have added the column concurrently.
   $columns=$db->query($mysql?'SHOW COLUMNS FROM personnel_bookings':'PRAGMA table_info(personnel_bookings)')->fetchAll(PDO::FETCH_ASSOC);
   if(!in_array('preferred_personnel',array_column($columns,$mysql?'Field':'name'),true))throw $e;
  }
 }
 destination_schema($db);
 personnel_registry_schema($db);
 $db->exec(($mysql?'INSERT IGNORE':'INSERT OR IGNORE').' INTO personnel_booking_assignments(booking_id,personnel_id) SELECT id,personnel_id FROM personnel_bookings WHERE personnel_id IS NOT NULL');
}
function personnel_query(): string {
 return 'SELECT b.*,u.full_name requester_name,o.name office_name,o.code office_code,p.full_name personnel_name,p.position personnel_position FROM personnel_bookings b JOIN users u ON u.id=b.requester_id JOIN offices o ON o.id=b.office_id LEFT JOIN personnel p ON p.id=b.personnel_id';
}
function personnel_booking(int $id,bool $lock=false): array {
 if($lock)lock_record('personnel_bookings',$id);
 $r=one(personnel_query().' WHERE b.id=?',[$id]);
 if(!$r||!visible($r))throw new RuntimeException('Personnel requisition not found or access denied.');
 return personnel_with_assignments([$r])[0];
}
function personnel_bookings(): array {
 global $user;
 $sql=personnel_query();$args=[];
 if(!is_role('Administrator','Administrative Officer','Dispatcher')){
  if(is_role('Supervisor')){$sql.=' WHERE b.office_id=?';$args[]=$user['office_id'];}
  else{$sql.=' WHERE b.requester_id=?';$args[]=$user['id'];}
 }
 return personnel_with_assignments(all($sql.' ORDER BY b.start_datetime DESC,b.id DESC',$args));
}
function personnel_with_assignments(array $rows): array {
 if(!$rows)return [];
 $assigned=[];
 foreach(array_chunk(array_column($rows,'id'),500) as $ids){
  $marks=implode(',',array_fill(0,count($ids),'?'));
  foreach(all("SELECT a.booking_id,p.* FROM personnel_booking_assignments a JOIN personnel p ON p.id=a.personnel_id WHERE a.booking_id IN ($marks) ORDER BY p.id",$ids) as $person)$assigned[$person['booking_id']][]=$person;
 }
 foreach($rows as &$row){
  $row['assigned_personnel']=$assigned[$row['id']]??[];
  if($row['assigned_personnel']){
   $row['personnel_name']=implode(', ',array_column($row['assigned_personnel'],'full_name'));
   $row['personnel_position']=implode(', ',array_column($row['assigned_personnel'],'position'));
  }
 }
 unset($row);return $rows;
}
function personnel_selection(array $input): array {
 $raw=$input['personnel_ids']??(isset($input['personnel_id'])?[$input['personnel_id']]:[]);
 if(!is_array($raw)||!$raw)throw new RuntimeException('Select at least one personnel member.');
 $ids=[];foreach($raw as $value){
  if((!is_string($value)&&!is_int($value))||!($id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])))throw new RuntimeException('Select valid personnel.');
  $ids[]=$id;
 }
 $ids=array_values(array_unique($ids));sort($ids,SORT_NUMERIC);return $ids;
}
function personnel_assignment_match(): string {
 return '(b.personnel_id=? OR EXISTS (SELECT 1 FROM personnel_booking_assignments a WHERE a.booking_id=b.id AND a.personnel_id=?))';
}
function personnel_assigned_ids(array $booking): array {
 $ids=array_map('intval',array_column($booking['assigned_personnel'],'id'));
 if(!$ids&&!empty($booking['personnel_id']))$ids=[(int)$booking['personnel_id']];
 sort($ids,SORT_NUMERIC);return $ids;
}
function approval_requests(): array {
 require_role('Administrator');
 $vehicles=array_map(fn($r)=>array_replace($r,['request_kind'=>'Vehicle']),array_values(array_filter(requests(),fn($r)=>in_array($r['status'],['Pending Supervisor','Pending Administrative Approval']))));
 $personnel=array_map(fn($r)=>array_replace($r,['request_kind'=>'Personnel']),array_values(array_filter(personnel_bookings(),fn($r)=>$r['status']==='Pending Administrative Approval')));
 $rows=[...$vehicles,...$personnel];
 usort($rows,fn($a,$b)=>strcmp($a['created_at'],$b['created_at'])?:strcmp($a['reference'],$b['reference']));
 return $rows;
}
function personnel_issues(array $person,array $booking,bool $includeTrips=true): array {
 global $pdo;
 $issues=[];
 if($includeTrips&&!empty($person['driver_id']))$issues=conflicts(0,(int)$person['driver_id'],$booking['start_datetime'],$booking['end_datetime'],0,false);
 if($person['status']!=='Available')$issues[]='Personnel is '.strtolower($person['status']).'.';
 $buffer=(int)setting('turnaround_minutes','30');
 $from=date('Y-m-d H:i:s',strtotime($booking['start_datetime'])-$buffer*60);
 $to=date('Y-m-d H:i:s',strtotime($booking['end_datetime'])+$buffer*60);
 $locking=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'&&$pdo->inTransaction()?' FOR UPDATE':'';
 if(one("SELECT b.id FROM personnel_bookings b WHERE ".personnel_assignment_match()." AND b.id<>? AND status IN ('Approved','In Progress') AND ((start_datetime < ? AND end_datetime > ?) OR (status='In Progress' AND end_datetime<=?))".$locking,[$person['id'],$person['id'],$booking['id']??0,$to,$from,date('Y-m-d H:i:s')]))$issues[]='Already assigned during this schedule or an earlier assignment is still in progress.';
 return $issues;
}
function personnel_notify(int $userId,string $message): void {
 run('INSERT INTO notifications(user_id,message,created_at) VALUES(?,?,?)',[$userId,$message,date('Y-m-d H:i:s')]);
}
function personnel_save(): int {
 global $user,$pdo;
 $id=number('id');
 if($id){$old=personnel_booking($id,true);if((int)$old['requester_id']!==(int)$user['id']||!in_array($old['status'],['Draft','Returned for Correction']))throw new RuntimeException('Only your drafts and returned requisitions can be edited.');}
 $start=datetime_value('start_datetime');$end=datetime_value('end_datetime');
 if($end<=$start)throw new RuntimeException('End time must be after start time.');
 if(!one("SELECT id FROM offices WHERE id=? AND status='Active'",[$user['office_id']]))throw new RuntimeException('Your office is inactive. Contact your administrator.');
 $status=field('submit_mode',20)==='draft'?'Draft':'Pending Administrative Approval';
 $values=[field('preferred_personnel',160,false),field('requested_role',160),destination_value(),field('purpose',5000),$start,$end,$status];
 if($id)run('UPDATE personnel_bookings SET preferred_personnel=?,requested_role=?,destination=?,purpose=?,start_datetime=?,end_datetime=?,status=? WHERE id=?',[...$values,$id]);
 else{
  run('INSERT INTO personnel_bookings(preferred_personnel,requested_role,destination,purpose,start_datetime,end_datetime,status,requester_id,office_id,created_at,reference) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[...$values,$user['id'],$user['office_id'],date('Y-m-d H:i:s'),'TMP-'.bin2hex(random_bytes(16))]);
  $id=(int)$pdo->lastInsertId();run('UPDATE personnel_bookings SET reference=? WHERE id=?',['PB-'.date('Y').'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT),$id]);
 }
 if($status==='Pending Administrative Approval')foreach(all("SELECT id FROM users WHERE status='Active' AND role='Administrator'") as $admin)personnel_notify((int)$admin['id'],'Personnel requisition #'.$id.' is awaiting administrator approval.');
 audit('Personnel requisition saved','Requisition #'.$id.' · '.$status);unset($_SESSION['old_input']);
 return $id;
}
function personnel_transition(int $id,string $action): void {
 global $user;
 $r=personnel_booking($id,true);$remark=field('remarks',2000,false);$new='';
 if(in_array($action,['approve','reject','return'])){
  require_role('Administrator');
  if($r['status']!=='Pending Administrative Approval')throw new RuntimeException('This requisition is no longer awaiting approval.');
  if(!password_verify(field('password',200),$user['password_hash']))throw new RuntimeException('The approval confirmation password is incorrect.');
  if($action==='approve'){
   $ids=personnel_selection($_POST);$people=[];
   foreach($ids as $personId){$person=personnel_lock($personId);if(!$person)throw new RuntimeException('Select valid personnel.');$people[]=$person;}
   foreach($people as $person){$issues=personnel_issues($person,$r);if($issues)throw new RuntimeException($person['full_name'].': '.implode(' ',$issues));}
   run('DELETE FROM personnel_booking_assignments WHERE booking_id=?',[$id]);
   foreach($ids as $personId)run('INSERT INTO personnel_booking_assignments(booking_id,personnel_id) VALUES(?,?)',[$id,$personId]);
   run('UPDATE personnel_bookings SET personnel_id=? WHERE id=?',[$ids[0],$id]);$new='Approved';
  }else{if($remark==='')throw new RuntimeException('Enter a reason for returning or rejecting this requisition.');$new=$action==='reject'?'Rejected':'Returned for Correction';}
 }elseif($action==='cancel'){
  if((int)$r['requester_id']!==(int)$user['id']||!in_array($r['status'],['Draft','Returned for Correction','Pending Administrative Approval','Approved']))throw new RuntimeException('This requisition cannot be cancelled.');
  $new='Cancelled';
 }elseif($action==='start'){
  require_role('Administrator','Dispatcher');
  if($r['status']!=='Approved')throw new RuntimeException('Only approved requisitions can be started.');
  $ids=personnel_assigned_ids($r);if(!$ids)throw new RuntimeException('Assign personnel before starting.');
  $people=[];foreach($ids as $personId){$person=personnel_lock($personId);if(!$person)throw new RuntimeException('Assigned personnel not found.');$people[]=$person;}
  $now=date('Y-m-d H:i:s');
  if($now<$r['start_datetime']||$now>=$r['end_datetime'])throw new RuntimeException('Start this assignment within its approved schedule.');
  foreach($people as $person){
   $issues=personnel_issues($person,$r);
   if(one("SELECT b.id FROM personnel_bookings b WHERE ".personnel_assignment_match()." AND status='In Progress' AND b.id<>?",[$person['id'],$person['id'],$id]))$issues[]='Personnel is currently on another assignment.';
   if($issues)throw new RuntimeException($person['full_name'].': '.implode(' ',$issues));
  }
  $new='In Progress';run('UPDATE personnel_bookings SET actual_start=? WHERE id=?',[$now,$id]);
 }elseif($action==='complete'){
  require_role('Administrator','Dispatcher');
  if($r['status']!=='In Progress')throw new RuntimeException('Only assignments in progress can be completed.');
  foreach(personnel_assigned_ids($r) as $personId)personnel_lock($personId);$new='Completed';run('UPDATE personnel_bookings SET actual_end=? WHERE id=?',[date('Y-m-d H:i:s'),$id]);
 }else throw new RuntimeException('Unknown personnel requisition action.');
 run('UPDATE personnel_bookings SET status=? WHERE id=?',[$new,$id]);
 run('INSERT INTO personnel_booking_history(booking_id,user_id,decision,remarks,created_at) VALUES(?,?,?,?,?)',[$id,$user['id'],$new,$remark,date('Y-m-d H:i:s')]);
 audit('Personnel requisition '.$new,$r['reference'].' · '.$remark);personnel_notify((int)$r['requester_id'],$r['reference'].' · '.$new);
}
