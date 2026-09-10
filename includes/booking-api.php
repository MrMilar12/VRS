<?php
require_once __DIR__.'/booking-assistant.php';
require_once __DIR__.'/assistant-tools.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');echo json_encode(['error'=>'POST required.']);return;}
check_csrf();
if(($_POST['mode']??'')==='reset'){unset($_SESSION['booking_chat']);if(($_SESSION['old_input']['return_to']??'')==='index.php?page=assistant')unset($_SESSION['old_input']);echo json_encode(['reset'=>true]);return;}
if(($_POST['mode']??'')==='refresh'){
 auth_limit('assistant-refresh',(string)$user['id'],180,3600);$saved=$_SESSION['booking_chat']??[];
 if(($saved['expires']??0)<time()||empty($saved['lookup_kind']))throw new RuntimeException('Ask for availability again to start a fresh check.');
 echo json_encode(['availability'=>assistant_availability($saved['lookup_kind'],$saved['lookup'])],JSON_THROW_ON_ERROR);return;
}
auth_limit('booking-chat',(string)$user['id'],20,3600);
$message=field('message',3000);
$state=$_SESSION['booking_chat']??[];
if(($state['expires']??0)<time())$state=[];
$messages=$state['messages']??[];$messages[]=['role'=>'user','content'=>$message];$messages=array_slice($messages,-12);
$current=$state['draft']??[];
if(isset($_POST['draft'])){try{$raw=json_decode(field('draft',20000),true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('The trip details could not be read. Please refresh and try again.');}if(!is_array($raw))throw new RuntimeException('Invalid trip details.');$current=booking_validate($raw)['draft'];}
$result=assistant_resolve(booking_respond($messages,$current),$current);
$result['show_review']=$result['ready']&&($result['intent']==='booking'||!empty($state['show_review']));
$messages[]=['role'=>'assistant','content'=>$result['reply']];
$_SESSION['booking_chat']=['messages'=>$messages,'draft'=>$result['draft'],'show_review'=>$result['show_review'],'lookup_kind'=>in_array($result['intent'],['vehicles','drivers'])?$result['intent']:null,'lookup'=>$result['lookup'],'expires'=>time()+1800];
echo json_encode($result,JSON_THROW_ON_ERROR);
