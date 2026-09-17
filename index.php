<?php
require __DIR__.'/includes/bootstrap.php';
if(!$user)redirect('login.php');
require __DIR__.'/includes/layout.php';
$page=$_GET['page']??'dashboard';
$pages=['delete'=>'Delete record','personnel'=>'Personnel','personnel-bookings'=>'Personnel requisitions','personnel-create'=>'New personnel requisition','personnel-request'=>'Personnel requisition details','developer'=>'Developer center','assistant'=>'AI booking assistant','profile'=>'My profile','dashboard'=>'Overview','calendar'=>'Vehicle calendar','requisitions'=>'Requisitions','create'=>'New requisition','request'=>'Requisition details','approvals'=>'Approvals','dispatch'=>'Dispatch & returns','vehicles'=>'Vehicles','drivers'=>'Drivers','offices'=>'Offices','users'=>'Users & roles','maintenance'=>'Maintenance','reports'=>'Reports & insights','audit'=>'Audit trail','settings'=>'Settings','notifications'=>'Notifications'];
if(!is_string($page)||!isset($pages[$page])){http_response_code(404);$page='notfound';}
try{
 foreach(['id','edit'] as $parameter)if(isset($_GET[$parameter]))$_GET[$parameter]=record_link_id($_GET[$parameter],$page.':'.$parameter);
 if(in_array($page,['vehicles','drivers','personnel','maintenance','reports']))require_role('Administrator','Administrative Officer');
 if($page==='drivers'){
  $target='index.php?page=personnel';
  if(isset($_GET['edit'])){$person=one('SELECT id FROM personnel WHERE driver_id=?',[(int)$_GET['edit']]);if(!$person)throw new RuntimeException('Personnel not found.');$target.='&edit='.$person['id'];}
  elseif(isset($_GET['add']))$target.='&add=1&classification=Driver';
  redirect(secure_record_url($target));
 }
 if(in_array($page,['offices','users','audit','settings','developer']))require_role('Administrator');
 if($page==='delete')deletion_record(is_string($_GET['entity']??null)?$_GET['entity']:'',(int)($_GET['id']??0));
 if($page==='approvals')require_role('Administrator');
 if($page==='dispatch')require_role('Administrator','Administrative Officer','Dispatcher');
}catch(RuntimeException $e){http_response_code(403);$page='forbidden';}
layout_start($page,$pages[$page]??'Page unavailable');
switch($page){
case 'delete':require __DIR__.'/includes/delete-view.php';break;
case 'personnel-bookings':case 'personnel-create':case 'personnel-request':require __DIR__.'/includes/personnel-view.php';break;
case 'developer': require __DIR__.'/includes/developer-view.php';break;
case 'assistant': require __DIR__.'/includes/booking-view.php';break;
case 'profile': require __DIR__.'/includes/profile.php';break;
case 'dashboard': require __DIR__.'/includes/dashboard.php';break;
case 'calendar': require __DIR__.'/includes/calendar.php';break;
case 'create': case 'request':require __DIR__.'/includes/requisition.php';break;
case 'requisitions':case 'approvals':case 'dispatch':require __DIR__.'/includes/request-list.php';break;
case 'personnel':case 'vehicles':case 'drivers':case 'offices':case 'users':require __DIR__.'/includes/record-page.php';break;
default:require __DIR__.'/includes/other-pages.php';
}
layout_end();
