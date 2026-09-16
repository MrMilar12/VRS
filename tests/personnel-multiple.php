<?php
require __DIR__.'/personnel.php';
// Simulate an old single-person record and verify repeatable backfill.
run('DELETE FROM personnel_booking_assignments WHERE booking_id=?',[$second]);personnel_schema($pdo);personnel_schema($pdo);
check_personnel(count(personnel_booking($second)['assigned_personnel'])===1,'Legacy assignment backfilled once');
foreach([[],['personnel_ids'=>'5'],['personnel_ids'=>[['5']]],['personnel_ids'=>['bad']]] as $invalid)deny_personnel(fn()=>personnel_selection($invalid),'Reject empty or malformed personnel selection');
$user=$owner;$_POST=array_replace($input,['start_datetime'=>date('Y-m-d\TH:i',strtotime('+40 days 09:00')),'end_datetime'=>date('Y-m-d\TH:i',strtotime('+40 days 12:00')),'submit_mode'=>'submit']);
lock_transaction();$groupId=personnel_save();finish_transaction(true);$user=$admin;
run("UPDATE personnel SET status='On Leave' WHERE id=5");
deny_personnel(fn()=>transition($groupId,'approve',['personnel_ids'=>[4,5],'password'=>'Demo@12345']),'Unavailable second member rejects the entire approval');
check_personnel(personnel_booking($groupId)['status']==='Pending Administrative Approval'&&!all('SELECT * FROM personnel_booking_assignments WHERE booking_id=?',[$groupId]),'Failed group approval saves no partial assignments');
run("UPDATE personnel SET status='Available' WHERE id=5");
deny_personnel(fn()=>transition($groupId,'approve',['personnel_ids'=>[4,999999],'password'=>'Demo@12345']),'Unknown member rejects group approval');
transition($groupId,'approve',['personnel_ids'=>[5,4,5],'password'=>'Demo@12345']);
$group=personnel_booking($groupId);
check_personnel(personnel_assigned_ids($group)===[4,5],'Multiple personnel saved once each in stable order');
check_personnel(str_contains($group['personnel_name'],'Miguel Santos')&&str_contains($group['personnel_name'],'Antonio Ramos'),'Details contain every assigned person');
$other=array_replace($group,['id'=>0]);$secondary=one('SELECT * FROM personnel WHERE id=5');
check_personnel((bool)personnel_issues($secondary,$other),'Secondary assignee blocks overlapping personnel requests');
check_personnel(!personnel_issues($secondary,$group),'Current requisition excluded for secondary assignee');
check_personnel((bool)driver_assignment_issues(one('SELECT * FROM drivers WHERE id=5'),$other),'Secondary assignee unavailable for vehicle trips');
$values=array_intersect_key($secondary,array_flip(['full_name','employee_number','office_id','position','contact','status','classification','license_number','license_expiry']));
lock_transaction();try{deny_personnel(fn()=>personnel_registry_values(array_replace($values,['status'=>'On Leave']),5),'Secondary assignee cannot be placed on leave while assigned');}finally{finish_transaction(false);}
run('UPDATE personnel_bookings SET start_datetime=?,end_datetime=? WHERE id=?',[date('Y-m-d H:i:s',time()-60),date('Y-m-d H:i:s',time()+3600),$groupId]);
run("UPDATE personnel SET status='On Leave' WHERE id=5");
deny_personnel(fn()=>transition($groupId,'start'),'Start validates all assigned members');
run("UPDATE personnel SET status='Available' WHERE id=5");transition($groupId,'start');
run('UPDATE personnel_bookings SET end_datetime=? WHERE id=?',[date('Y-m-d H:i:s',time()-1),$groupId]);
check_personnel((bool)personnel_issues($secondary,$other),'Overdue group assignment blocks secondary member');
transition($groupId,'complete');
check_personnel(!personnel_issues($secondary,$other),'Completion releases secondary member');
$user=$owner;$_POST=array_replace($input,['start_datetime'=>$other['start_datetime'],'end_datetime'=>$other['end_datetime'],'submit_mode'=>'submit']);
// The form parser expects datetime-local values.
$_POST['start_datetime']=date('Y-m-d\TH:i',strtotime($other['start_datetime']));$_POST['end_datetime']=date('Y-m-d\TH:i',strtotime($other['end_datetime']));
lock_transaction();$cancelId=personnel_save();finish_transaction(true);$user=$admin;
transition($cancelId,'approve',['personnel_ids'=>[4,5],'password'=>'Demo@12345']);$user=$owner;transition($cancelId,'cancel');
check_personnel(!personnel_issues($secondary,$other),'Cancellation releases all selected personnel');
echo "All multiple personnel checks passed.\n";
