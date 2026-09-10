<?php
function initialize_database(PDO $db): void {
    $db->exec(file_get_contents(__DIR__.'/../database/schema.sqlite.sql'));
    if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()>0) return;
    seed_database($db);
}
function seed_database(PDO $db): void {
    $insert=function($table,$data)use($db){$q=$db->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')');$q->execute(array_values($data));return (int)$db->lastInsertId();};
    $db->beginTransaction();
    try {
    foreach ([['ADM','Administrative Office','Maria Santos'],['PLN','Planning & Development','Daniel Milar'],['ENG','Engineering Office','Roberto Cruz'],['GSO','General Services Office','Ana Flores'],['HRM','Human Resource Office','Carla Reyes']] as $o) $insert('offices',['code'=>$o[0],'name'=>$o[1],'head'=>$o[2],'supervisor'=>$o[2],'contact'=>'(042) 209-3100','status'=>'Active']);
    $password=password_hash('Demo@12345',PASSWORD_DEFAULT);
    foreach ([['Daniel Milar','daniel@vrs.local','Administrator',1],['Maria Santos','supervisor@vrs.local','Supervisor',1],['Ana Flores','requester@vrs.local','Requester',1],['Carla Reyes','admin@vrs.local','Administrative Officer',1],['Roberto Cruz','dispatch@vrs.local','Dispatcher',1]] as $u) $insert('users',['full_name'=>$u[0],'email'=>$u[1],'password_hash'=>$password,'role'=>$u[2],'office_id'=>$u[3],'position'=>'Government Personnel','status'=>'Active']);
    foreach ([['Toyota Hiace','Van','SAB 1234',15,28450,'Available'],['Toyota Innova','MPV','SAC 5678',7,18320,'Available'],['Mitsubishi Montero','SUV','SAD 9012',7,35870,'Available'],['Isuzu D-Max','Pickup','SAE 3456',5,42100,'Available'],['Toyota Hiace Grandia','Van','SAF 7890',12,12340,'Available'],['Nissan Navara','Pickup','SAG 2345',5,31500,'Under Maintenance']] as $i=>$v) $insert('vehicles',['model'=>$v[0],'type'=>$v[1],'plate'=>$v[2],'property_number'=>'GOV-2024-00'.($i+1),'capacity'=>$v[3],'odometer'=>$v[4],'registration_expiry'=>date('Y-m-d',strtotime('+8 months')),'condition_text'=>'Good','status'=>$v[5],'photo_url'=>'']);
    foreach (['Juan Dela Cruz','Pedro Reyes','Roberto Garcia','Miguel Santos','Antonio Ramos'] as $i=>$name) $insert('drivers',['full_name'=>$name,'employee_number'=>'DRV-00'.($i+1),'office_id'=>4,'contact'=>'0917 555 010'.($i+1),'license_number'=>'N01-20-00000'.($i+1),'license_expiry'=>date('Y-m-d',strtotime('+2 years')),'status'=>'Available']);
    $today=date('Y-m-d');
    $trips=[['Regional coordination meeting','Baler, Aurora',1,1,2,'Approved','08:00','17:00',0],['Project site inspection','San Luis, Aurora',4,3,3,'Dispatched','07:30','16:30',0],['Procurement of office supplies','Cabanatuan City',2,2,4,'Approved','09:00','15:00',0],['Inter-agency planning workshop','Dingalan, Aurora',5,4,2,'Pending Administrative Approval','08:00','17:00',1],['Personnel training and development','Regional Office, Pampanga',null,null,1,'Pending Administrative Approval','06:00','18:00',2],['Delivery of project materials','Maria Aurora',4,3,3,'Completed','08:00','16:00',-2],['Community outreach program','Dipaculao, Aurora',1,1,5,'Approved','08:00','17:00',3],['Quarterly budget consultation','Baler Municipal Hall',null,null,1,'Pending Administrative Approval','09:00','12:00',1]];
    foreach($trips as $i=>$t){$day=date('Y-m-d',strtotime($today.' '.$t[8].' days'));$id=$insert('requisitions',['reference'=>'VR-'.date('Y').'-'.str_pad((string)($i+1),4,'0',STR_PAD_LEFT),'requester_id'=>$i===4||$i===7?3:1,'office_id'=>$t[4],'vehicle_type'=>$t[2]===4?'Pickup':'Van','vehicle_id'=>$t[2],'driver_id'=>$t[3],'preferred_driver'=>'','passengers'=>'Daniel Milar, Maria Santos','start_datetime'=>$day.' '.$t[6].':00','end_datetime'=>$day.' '.$t[7].':00','destination'=>$t[1],'purpose'=>$t[0],'fuel_allocation'=>1,'fuel_quantity'=>20,'fuel_remarks'=>'Official travel','status'=>$t[5],'created_at'=>date('Y-m-d H:i:s',strtotime('-3 days'))]);
    if(in_array($t[5],['Approved','Dispatched','Completed'])){foreach(['Supervisor','Administrative'] as $stage)$insert('approvals',['requisition_id'=>$id,'user_id'=>1,'stage'=>$stage,'decision'=>'Approved','remarks'=>'Approved for official travel.','created_at'=>date('Y-m-d H:i:s',strtotime('-1 day'))]);}
    if(in_array($t[5],['Dispatched','Completed']))$insert('vehicle_movements',['requisition_id'=>$id,'departure'=>$day.' 07:30:00','return_time'=>$t[5]==='Completed'?$day.' 16:00:00':null,'odometer_out'=>$t[5]==='Completed'?41950:42100,'odometer_in'=>$t[5]==='Completed'?42100:null,'condition_text'=>'Good','remarks'=>'','checked_by'=>1]);
    }
    $insert('vehicle_blocks',['vehicle_id'=>6,'start_datetime'=>$today.' 00:00:00','end_datetime'=>date('Y-m-d',strtotime('+3 days')).' 23:59:00','reason'=>'Preventive maintenance • oil and brake service','created_by'=>1]);
    foreach(['turnaround_minutes'=>'30','organization'=>'Provincial Government of Aurora','supervisor_signatory'=>'Immediate Supervisor','admin_signatory'=>'Administrative Officer'] as $k=>$v)$insert('system_settings',['setting_key'=>$k,'setting_value'=>$v]);
    $insert('audit_logs',['user_id'=>1,'action'=>'System initialized','details'=>'Sample fleet and requisitions loaded','created_at'=>date('Y-m-d H:i:s')]);
    $db->commit();
    }catch(Throwable $e){$db->rollBack();throw $e;}
}
