<?php
require __DIR__.'/includes/bootstrap.php';
if(!$user)redirect('login.php');
require __DIR__.'/includes/layout.php';
$page=$_GET['page']??'dashboard';
$pages=['dashboard'=>'Overview','calendar'=>'Vehicle calendar','requisitions'=>'Requisitions','create'=>'New requisition','request'=>'Requisition details','approvals'=>'Approvals','dispatch'=>'Dispatch & returns','vehicles'=>'Vehicles','drivers'=>'Drivers','offices'=>'Offices','users'=>'Users & roles','maintenance'=>'Maintenance','reports'=>'Reports & insights','audit'=>'Audit trail','settings'=>'Settings','notifications'=>'Notifications'];
if(!is_string($page)||!isset($pages[$page])){http_response_code(404);$page='notfound';}
try{
 if(in_array($page,['vehicles','drivers','maintenance','reports']))require_role('Administrator','Administrative Officer');
 if(in_array($page,['offices','users','audit','settings']))require_role('Administrator');
 if($page==='approvals')require_role('Administrator','Supervisor','Administrative Officer');
 if($page==='dispatch')require_role('Administrator','Administrative Officer','Dispatcher');
}catch(RuntimeException $e){http_response_code(403);$page='forbidden';}
layout_start($page,$pages[$page]??'Page unavailable');
switch($page){
case 'dashboard': require __DIR__.'/includes/dashboard.php';break;
case 'calendar': require __DIR__.'/includes/calendar.php';break;
case 'create': case 'request':require __DIR__.'/includes/requisition.php';break;
case 'requisitions':case 'approvals':case 'dispatch':require __DIR__.'/includes/request-list.php';break;
case 'vehicles':case 'drivers':case 'offices':case 'users':require __DIR__.'/includes/record-page.php';break;
default:require __DIR__.'/includes/other-pages.php';
}
layout_end();
