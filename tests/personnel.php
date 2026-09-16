<?php
// Disposable database: workflow, authorization, and availability regression checks.
date_default_timezone_set('Asia/Manila');
require __DIR__.'/../includes/functions.php';require __DIR__.'/../includes/database.php';require __DIR__.'/../includes/personnel.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');initialize_database($pdo);personnel_schema($pdo);personnel_schema($pdo);
// Exercise upgrades from an existing installation as well as repeated startup.
$pdo->exec('ALTER TABLE personnel_bookings DROP COLUMN preferred_personnel');personnel_schema($pdo);personnel_schema($pdo);
function check_personnel(bool $ok,string $message): void {if(!$ok)throw new RuntimeException('FAIL: '.$message);echo 'PASS: '.$message."\n";}
function transition(int $id,string $action,array $input=[]): void {$_POST=$input;lock_transaction();try{personnel_transition($id,$action);finish_transaction(true);}catch(Throwable $e){finish_transaction(false);throw $e;}}
function deny_personnel(callable $fn,string $message): void {try{$fn();}catch(RuntimeException $e){echo 'PASS: '.$message."\n";return;}throw new RuntimeException('FAIL: '.$message);}
run("INSERT INTO personnel(full_name,employee_number,office_id,position,status) VALUES('Test staff','P001',1,'Technician','Available')");
$staffId=(int)$pdo->lastInsertId();$staff=one('SELECT * FROM personnel WHERE id=?',[$staffId]);$admin=one('SELECT * FROM users WHERE id=1');$user=one('SELECT * FROM users WHERE id=3');$owner=$user;
$start=date('Y-m-d\TH:i',strtotime('+2 days 09:00'));$end=date('Y-m-d\TH:i',strtotime('+2 days 12:00'));
$_POST=['id'=>'0','requested_role'=>'Technician','destination'=>'Office','purpose'=>'Equipment inspection','start_datetime'=>$start,'end_datetime'=>$end,'submit_mode'=>'draft'];$input=$_POST;
lock_transaction();$id=personnel_save();finish_transaction(true);
check_personnel(personnel_booking($id)['status']==='Draft','Save personnel draft without vehicle or driver');
check_personnel(personnel_booking($id)['preferred_personnel']==='','Preference is optional');
$_POST=array_replace($input,['id'=>(string)$id,'submit_mode'=>'submit','preferred_personnel'=>'Preferred staff']);lock_transaction();personnel_save();finish_transaction(true);
check_personnel(personnel_booking($id)['preferred_personnel']==='Preferred staff','Preference persists on submission');
check_personnel(personnel_booking($id)['personnel_id']===null,'Preference does not assign personnel');
check_personnel(personnel_booking($id)['status']==='Pending Administrative Approval','Submit draft for administrator approval');
deny_personnel(fn()=>transition($id,'approve',['personnel_id'=>$staffId,'password'=>'Demo@12345']),'Requester cannot approve own booking');
$user=array_replace(one('SELECT * FROM users WHERE id=2'),['office_id'=>2]);deny_personnel(fn()=>personnel_booking($id),'Other office cannot read private personnel booking');
$user=$admin;
deny_personnel(fn()=>transition($id,'approve',['personnel_id'=>$staffId,'password'=>'wrong']),'Approval requires correct password');
deny_personnel(fn()=>transition($id,'reject',['password'=>'Demo@12345']),'Rejection requires remarks');
transition($id,'return',['password'=>'Demo@12345','remarks'=>'Clarify duties']);
$user=$owner;$_POST=array_replace($input,['id'=>(string)$id,'submit_mode'=>'submit']);lock_transaction();personnel_save();finish_transaction(true);$user=$admin;
transition($id,'approve',['personnel_id'=>$staffId,'password'=>'Demo@12345']);
check_personnel(personnel_booking($id)['personnel_id']===$staffId,'Administrator assigns available personnel');
deny_personnel(fn()=>transition($id,'approve',['personnel_id'=>$staffId,'password'=>'Demo@12345']),'Already approved booking cannot be approved again');
$r=personnel_booking($id);check_personnel(!personnel_issues($staff,$r),'Availability excludes current booking');
$other=array_replace($r,['id'=>0]);check_personnel((bool)personnel_issues($staff,$other),'Overlapping personnel booking blocked');
$other['start_datetime']=date('Y-m-d H:i:s',strtotime($r['end_datetime'])+29*60);$other['end_datetime']=date('Y-m-d H:i:s',strtotime($r['end_datetime'])+120*60);
check_personnel((bool)personnel_issues($staff,$other),'Personnel turnaround buffer enforced');$other['start_datetime']=date('Y-m-d H:i:s',strtotime($r['end_datetime'])+30*60);check_personnel(!personnel_issues($staff,$other),'Exact buffer boundary becomes available');
check_personnel((bool)personnel_issues(array_replace($staff,['status'=>'On Leave']),$r),'Personnel on leave cannot be assigned');
$user=$owner;$_POST=array_replace($input,['submit_mode'=>'submit']);lock_transaction();$second=personnel_save();finish_transaction(true);$user=$admin;
deny_personnel(fn()=>transition($second,'approve',['personnel_id'=>$staffId,'password'=>'Demo@12345']),'Approval rechecks conflicting bookings on server');
$user=$owner;transition($id,'cancel');check_personnel(!personnel_issues($staff,personnel_booking($second)),'Cancellation releases personnel availability');
$user=$admin;transition($second,'approve',['personnel_id'=>$staffId,'password'=>'Demo@12345']);
deny_personnel(fn()=>transition($second,'start'),'Assignment cannot start before approved schedule');
run('UPDATE personnel_bookings SET start_datetime=?,end_datetime=? WHERE id=?',[date('Y-m-d H:i:s',time()-60),date('Y-m-d H:i:s',time()+3600),$second]);
transition($second,'start');check_personnel(personnel_booking($second)['status']==='In Progress','Approved assignment can start within schedule');
run('UPDATE personnel_bookings SET end_datetime=? WHERE id=?',[date('Y-m-d H:i:s',time()-1),$second]);check_personnel((bool)personnel_issues($staff,$other),'Overdue in-progress assignment remains unavailable');
transition($second,'complete');check_personnel(!personnel_issues($staff,$other),'Completion releases personnel');
check_personnel((bool)personnel_booking($second)['actual_end'],'Completion timestamp recorded');
check_personnel(count(all('SELECT * FROM personnel_booking_history WHERE booking_id=?',[$second]))===3,'Approval and progress decisions recorded');
check_personnel((bool)all("SELECT * FROM notifications WHERE user_id=? AND requisition_id IS NULL",[$owner['id']]),'Personnel workflow notifies requester');
echo "All personnel checks passed.\n";
