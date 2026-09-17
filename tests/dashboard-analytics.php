<?php
require __DIR__.'/../includes/dashboard-analytics.php';
function check_analytics(bool $value,string $label): void {if(!$value)throw new RuntimeException($label);echo "PASS: $label\n";}
$base=['created_at'=>'2026-09-01 10:00:00','status'=>'Approved','office_name'=>'Office A'];
$v=[$base,array_replace($base,['created_at'=>'2026-02-01 10:00:00','status'=>'Completed'])];
$p=[array_replace($base,['status'=>'In Progress','office_name'=>'Office B']),array_replace($base,['status'=>'Cancelled'])];
$a=dashboard_analytics($v,$p,'all','all','2026-09-17');
check_analytics($a['total']===4&&$a['vehicle']===2&&$a['personnel']===2,'Combined totals count both requisition types once');
check_analytics($a['open']===2&&$a['completed']===1,'Returned and active records are not counted as completed');
check_analytics(count($a['months'])===8&&$a['months']['2026-03']===['vehicle'=>0,'personnel'=>0],'Empty months filled without distorting trend');
check_analytics($a['offices']===['Office A'=>3,'Office B'=>1],'Office counts reconcile to requisition total');
$a=dashboard_analytics($v,$p,'personnel','6','2026-09-17');check_analytics($a['total']===2&&$a['vehicle']===0&&count($a['months'])===6,'Type and period filters applied together');
$a=dashboard_analytics($v,$p,'all','6','2026-09-17');check_analytics($a['total']===3,'Period uses request creation dates');
$a=dashboard_analytics([],[],'all','all','2026-09-17');check_analytics($a['total']===0&&count($a['months'])===1,'Empty data gives zero totals and current month');
echo "All analytics checks passed.\n";
