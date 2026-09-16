<?php
// Disposable legacy installation: migration, classification, and shared availability.
date_default_timezone_set('Asia/Manila');
require __DIR__.'/../includes/functions.php';require __DIR__.'/../includes/database.php';require __DIR__.'/../includes/personnel.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec('PRAGMA foreign_keys=ON');initialize_database($pdo);
$pdo->exec("CREATE TABLE personnel(id INTEGER PRIMARY KEY AUTOINCREMENT,full_name VARCHAR(160) NOT NULL,employee_number VARCHAR(80) NOT NULL UNIQUE,office_id INTEGER NOT NULL,position VARCHAR(160) NOT NULL,contact VARCHAR(80),status VARCHAR(40) NOT NULL)");
run("INSERT INTO personnel(full_name,employee_number,office_id,position,status) VALUES('Existing staff','DRV-001',1,'Senior staff','Available')");
$trips=all('SELECT id,driver_id FROM requisitions ORDER BY id');
personnel_schema($pdo);personnel_schema($pdo);
function registry_check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label."\n";}
function registry_save(array $values,int $id): void {
 lock_transaction();try{$values=personnel_registry_values($values,$id);run('UPDATE personnel SET '.implode(',',array_map(fn($key)=>$key.'=?',array_keys($values))).' WHERE id=?',[...array_values($values),$id]);finish_transaction(true);}catch(Throwable $e){finish_transaction(false);throw $e;}
}
function registry_denied(callable $action,string $label): void {try{$action();}catch(RuntimeException $e){registry_check(true,$label);return;}throw new RuntimeException($label);}
registry_check(count(all('SELECT * FROM personnel'))===5,'Legacy drivers imported once and matching employee numbers combined');
$person=one('SELECT * FROM personnel WHERE id=1');
registry_check($person['full_name']==='Existing staff'&&$person['classification']==='Driver'&&(int)$person['driver_id']===1,'Existing personnel identity retained during merge');
registry_check(one('SELECT full_name FROM drivers WHERE id=1')['full_name']==='Existing staff','Merged staff identity is consistent in driving records');
registry_check($trips===all('SELECT id,driver_id FROM requisitions ORDER BY id'),'Historical trip references preserved');
$user=one('SELECT * FROM users WHERE id=1');
$person=one('SELECT * FROM personnel WHERE driver_id=5');$id=(int)$person['id'];
$values=array_intersect_key($person,array_flip(['full_name','employee_number','office_id','position','contact','status','classification','license_number','license_expiry']));
registry_denied(fn()=>registry_save(array_replace($values,['license_number'=>'']),$id),'Driver requires license details');
registry_save(array_replace($values,['full_name'=>'Renamed driver']),$id);
registry_check(one('SELECT full_name FROM drivers WHERE id=5')['full_name']==='Renamed driver','Unified edits reach vehicle assignments');
$day=date('Y-m-d',strtotime('+20 days'));
$booking=['id'=>0,'start_datetime'=>$day.' 09:00:00','end_datetime'=>$day.' 12:00:00'];
run("INSERT INTO requisitions(reference,requester_id,office_id,vehicle_type,driver_id,passengers,start_datetime,end_datetime,destination,purpose,status,created_at) VALUES('SHARED-TRIP',1,1,'Van',5,'Staff',?,?,'Office','Work','Approved',?)",[$booking['start_datetime'],$booking['end_datetime'],date('Y-m-d H:i:s')]);
$tripId=(int)$pdo->lastInsertId();
registry_check((bool)personnel_issues($person,$booking),'Vehicle trip blocks personnel assignment');
registry_denied(fn()=>registry_save(array_replace($values,['classification'=>'Utility']),$id),'Upcoming trip prevents changing driver to utility');
run("UPDATE requisitions SET status='Completed' WHERE id=?",[$tripId]);
$_POST=['id'=>'0','requested_role'=>'Staff','destination'=>'Office','purpose'=>'Work','start_datetime'=>$day.'T09:00','end_datetime'=>$day.'T12:00','submit_mode'=>'submit'];
lock_transaction();$bookingId=personnel_save();$_POST=['personnel_id'=>$id,'password'=>'Demo@12345'];personnel_transition($bookingId,'approve');finish_transaction(true);
registry_check((bool)driver_assignment_issues(one('SELECT * FROM drivers WHERE id=5'),$booking),'Personnel assignment blocks driving assignment');
registry_denied(fn()=>registry_save(array_replace($values,['status'=>'On Leave']),$id),'Active personnel assignment prevents leave');
registry_save(array_replace($values,['classification'=>'Utility','license_number'=>'','license_expiry'=>'']),$id);
registry_check((bool)driver_assignment_issues(one('SELECT * FROM drivers WHERE id=5'),$booking),'Utility classification cannot be assigned as driver');
personnel_schema($pdo);
registry_check(one('SELECT classification FROM personnel WHERE id=?',[$id])['classification']==='Utility','Startup preserves reclassification and historical driver link');
registry_save($values,$id);
registry_check((int)one('SELECT driver_id FROM personnel WHERE id=?',[$id])['driver_id']===5,'Reclassifying as driver reuses original trip identity');
echo "All unified personnel checks passed.\n";
