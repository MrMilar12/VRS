<?php
require __DIR__.'/../includes/functions.php';require __DIR__.'/../includes/database.php';require __DIR__.'/../includes/booking-assistant.php';require __DIR__.'/../includes/assistant-tools.php';
date_default_timezone_set('Asia/Manila');
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);initialize_database($pdo);
function check_tool(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label."\n";}
run("DELETE FROM requisitions");run('DELETE FROM vehicle_blocks');
$lookup=['search'=>'SAB 1234','status'=>'all','start_datetime'=>date('Y-m-d',strtotime('+2 days')).'T08:00','end_datetime'=>date('Y-m-d',strtotime('+2 days')).'T17:00'];
$r=assistant_availability('vehicles',$lookup);check_tool(count($r['items'])===1&&$r['items'][0]['available'],'Plate search returns an available vehicle');
run("INSERT INTO requisitions(reference,requester_id,office_id,vehicle_type,vehicle_id,driver_id,passengers,start_datetime,end_datetime,destination,purpose,status,created_at) VALUES('SECRET-TRIP',1,1,'Van',1,1,'PRIVATE PASSENGER',?,?,'PRIVATE DESTINATION','PRIVATE PURPOSE','Approved',?)",[str_replace('T',' ',$lookup['start_datetime']).':00',str_replace('T',' ',$lookup['end_datetime']).':00',date('Y-m-d H:i:s')]);
$r=assistant_availability('vehicles',$lookup);check_tool(!$r['items'][0]['available'],'Approved trips block availability');
$encoded=json_encode($r);check_tool(!str_contains($encoded,'PRIVATE')&&!str_contains($encoded,'SECRET-TRIP'),'Availability never reveals private trip details or references');
check_tool(!assistant_availability('drivers',array_replace($lookup,['search'=>'Juan']))['items'][0]['available'],'Driver booking conflicts are checked');
check_tool(!assistant_availability('vehicles',array_replace($lookup,['status'=>'available']))['items'],'Available filter excludes occupied vehicles');
run("UPDATE requisitions SET status='Completed'");check_tool(assistant_availability('vehicles',$lookup)['items'][0]['available'],'Completed trips release availability');
run("UPDATE vehicles SET registration_expiry='2000-01-01' WHERE id=1");check_tool(!assistant_availability('vehicles',$lookup)['items'][0]['available'],'Expired registrations block vehicles');
run("UPDATE drivers SET status='On Leave' WHERE id=1");check_tool(!assistant_availability('drivers',array_replace($lookup,['search'=>'Juan']))['items'][0]['available'],'Drivers on leave are unavailable');
run("UPDATE drivers SET status='Available',license_expiry='2000-01-01' WHERE id=1");check_tool(!assistant_availability('drivers',array_replace($lookup,['search'=>'Juan']))['items'][0]['available'],'Expired licenses block drivers');
run('INSERT INTO vehicle_blocks(vehicle_id,start_datetime,end_datetime,reason) VALUES(2,?,?,?)',[str_replace('T',' ',$lookup['start_datetime']).':00',str_replace('T',' ',$lookup['end_datetime']).':00','PRIVATE MAINTENANCE NOTE']);
check_tool(!assistant_availability('vehicles',array_replace($lookup,['search'=>'SAC 5678']))['items'][0]['available'],'Scheduled maintenance blocks vehicles');
check_tool(assistant_availability('vehicles',['search'=>'','status'=>'all'])['live'],'No dates checks current status');
check_tool(assistant_availability('vehicles',['start_datetime'=>$lookup['start_datetime']])['needs_dates'],'Incomplete intervals ask for both dates');
$current=booking_validate(['destination'=>'Baler'])['draft'];$reply=assistant_resolve(['intent'=>'system','topic'=>'approval','lookup'=>[],'draft'=>['destination'=>'Invented'],'reply'=>'Invented answer'],$current);
check_tool($reply['draft']['destination']==='Baler'&&!$reply['ready']&&str_contains($reply['reply'],'Only the Administrator'),'System questions preserve booking progress and use verified permissions');
check_tool(str_contains(assistant_help('authenticator'),'My profile'),'Authenticator help explains real profile controls');
