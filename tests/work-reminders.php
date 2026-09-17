<?php
require __DIR__.'/../includes/work-reminders.php';
function check_work(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
$base=['id'=>1,'reference'=>'TEST-1','requester_id'=>3,'start_datetime'=>'2026-09-17 11:00:00','end_datetime'=>'2026-09-17 12:00:00','status'=>'Approved'];
$today='2026-09-17 10:00:00';
$work=work_reminders([$base],[$base],$today,3);
check_work(count($work)===2&&$work[0]['reminder_task']==='Needs dispatch'&&$work[1]['reminder_task']==='Needs start','Today includes approved vehicle and personnel actions before start');
$work=work_reminders([],[$base],'2026-09-18 10:00:00',3);
check_work(count($work)===1&&$work[0]['carryover']&&$work[0]['overdue']&&$work[0]['reminder_task']==='Review missed schedule','Forgotten request persists the next day after schedule ends');
check_work(count(work_reminders([],[$base],'2026-10-20 10:00:00',3))===1,'Unresolved request remains visible without an age cutoff');
foreach(['Completed','Cancelled','Rejected'] as $status)check_work(!work_reminders([], [array_replace($base,['status'=>$status])],$today,3),"Resolved $status request disappears");
foreach(['Pending Administrative Approval','Returned for Correction','Draft','In Progress','Returned','Dispatched'] as $status)check_work(count(work_reminders([array_replace($base,['status'=>$status])],[],$today,3))===1,"Unfinished $status remains visible");
$future=array_replace($base,['start_datetime'=>'2026-09-20 10:00:00','end_datetime'=>'2026-09-20 12:00:00']);
$futureWork=work_reminders([],[$future],$today,3);check_work(count($futureWork)===1&&$futureWork[0]['work_group']==='upcoming','Future assignment appears in upcoming group');
check_work(work_reminders([$future],[],$today,3)[0]['work_group']==='upcoming','Future vehicle trip appears in upcoming group');
check_work(work_reminders([],[$future],'2026-09-20 09:00:00',3)[0]['work_group']==='today','Upcoming task moves into today on its scheduled day');
check_work(work_reminders([],[$future],'2026-09-21 09:00:00',3)[0]['work_group']==='unfinished','Forgotten upcoming task carries over after scheduled day');
check_work(work_reminders([],[array_replace($future,['status'=>'In Progress'])],$today,3)[0]['work_group']==='today','Assignment started early appears immediately');
check_work(!work_reminders([],[array_replace($base,['status'=>'Draft'])],$today,1),'Another user draft is not presented as administrator work');
$old=array_replace($base,['reference'=>'OLD','start_datetime'=>'2026-09-16 10:00:00','end_datetime'=>'2026-09-16 11:00:00']);
$work=work_reminders([],[$base,$old],$today,3);check_work($work[0]['reference']==='OLD','Overdue work appears first');
echo "All work reminder checks passed.\n";
