<?php
require __DIR__.'/includes/bootstrap.php';
if(!$user)redirect('login.php');if(!manage()){http_response_code(403);exit('Access denied.');}
require __DIR__.'/includes/report-data.php';
header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="vrs-trips-'.$from.'-to-'.$to.'.csv"');
$out=fopen('php://output','w');fputcsv($out,['Reference','Requester','Office','Destination','Purpose','Start','Estimated return','Vehicle','Plate','Driver','Status','Fuel liters requested','Distance km','Actual return','Late']);
foreach($reportRows as $r){$row=[$r['reference'],$r['requester_name'],$r['office_name'],$r['destination'],$r['purpose'],$r['start_datetime'],$r['end_datetime'],$r['model'],$r['plate'],$r['driver_name'],$r['status'],$r['fuel_quantity'],$r['distance'],$r['return_time'],$r['late']?'Yes':'No'];fputcsv($out,array_map(fn($v)=>preg_match('/^[\s]*[=+@-]/u',(string)$v)?"'".$v:$v,$row));}fclose($out);audit('Report exported',$from.' to '.$to);
