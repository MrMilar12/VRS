<?php
// Questions from the header have their own conversation, separate from booking drafts.
auth_limit('header-assistant',(string)$user['id'],40,3600);
$message=field('message',3000);$state=$_SESSION['header_assistant']??[];
if(($state['expires']??0)<time())$state=[];
$messages=$state['messages']??[];$messages[]=['role'=>'user','content'=>$message];$messages=array_slice($messages,-12);
$result=assistant_resolve(assistant_local_reply($message,[])??booking_respond($messages,[]),[],$state['search']??'');
if($result['intent']==='booking')$result['reply']='I can help you prepare that trip in Booking & help assistant. Open the full assistant below to enter and review your booking details. Nothing has been submitted.';
$messages[]=['role'=>'assistant','content'=>$result['reply']];
$_SESSION['header_assistant']=['messages'=>$messages,'search'=>$result['tracking']['search']??($state['search']??''),'expires'=>time()+1800];
echo json_encode(['reply'=>$result['reply'],'items'=>$result['tracking']['items']??[],'availability'=>$result['availability']],JSON_THROW_ON_ERROR);
