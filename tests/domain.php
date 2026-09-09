<?php
// Isolated in-memory checks; does not touch the application's database.
date_default_timezone_set('Asia/Manila');
require __DIR__.'/../includes/database.php';require __DIR__.'/../includes/functions.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$pdo->exec('PRAGMA foreign_keys=ON');initialize_database($pdo);$user=one('SELECT * FROM users WHERE id=1');
function verify(bool $condition,string $label):void{if(!$condition)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";}
verify(count(all('SELECT * FROM vehicles'))===6,'Demo fleet seeded');
run("DELETE FROM requisitions WHERE status NOT IN ('Completed','Dispatched','Approved')");
$day=date('Y-m-d',strtotime('+10 days'));
run("INSERT INTO requisitions(reference,requester_id,office_id,vehicle_type,vehicle_id,driver_id,passengers,start_datetime,end_datetime,destination,purpose,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)",['TEST',1,1,'SUV',3,5,'Test passenger',$day.' 08:00:00',$day.' 12:00:00','Test','Test','Approved',date('Y-m-d H:i:s')]);$id=(int)$pdo->lastInsertId();
verify((bool)conflicts(3,4,$day.' 10:00:00',$day.' 14:00:00'),'Vehicle overlap blocked');
verify((bool)conflicts(2,5,$day.' 10:00:00',$day.' 14:00:00'),'Driver overlap blocked');
verify((bool)conflicts(3,4,$day.' 12:15:00',$day.' 15:00:00'),'Turnaround buffer enforced');
verify(!conflicts(3,5,$day.' 12:30:00',$day.' 15:00:00'),'Exactly adjacent buffered schedule allowed');
verify(!conflicts(3,5,$day.' 08:00:00',$day.' 12:00:00',$id),'Current request excluded from conflict checks');
run('INSERT INTO vehicle_blocks(vehicle_id,start_datetime,end_datetime,reason,created_by) VALUES(?,?,?,?,?)',[2,$day.' 08:00:00',$day.' 12:00:00','Test service',1]);
verify((bool)conflicts(2,4,$day.' 09:00:00',$day.' 10:00:00'),'Maintenance overlap blocked');
$user=one('SELECT * FROM users WHERE id=3');verify(!visible(one('SELECT * FROM requisitions WHERE id=?',[$id])),'Requester cannot see another requester’s record');
verify(e('<script>"&')==='&lt;script&gt;&quot;&amp;','HTML output escaped');
$_POST=['start'=>'2026-02-30T10:00'];try{datetime_value('start');throw new Exception('Invalid date accepted');}catch(RuntimeException $e){echo "PASS: Invalid calendar date rejected\n";}
echo "All domain checks passed.\n";
