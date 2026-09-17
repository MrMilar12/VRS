<?php
// Search only records the signed-in user is authorized to view, on every turn.
function assistant_track(array $lookup,string $previous=''): array {
 $lookup=assistant_lookup_validate($lookup);$search=$lookup['search']!==''?$lookup['search']:$previous;
 if($search==='')return ['search'=>'','items'=>[],'reply'=>'Which request would you like to track? Send its slip reference, a personnel name, or a destination.'];
 $records=[...array_map(fn($r)=>array_replace($r,['kind'=>'Vehicle']),requests()),...array_map(fn($r)=>array_replace($r,['kind'=>'Personnel']),personnel_bookings())];
 $exact=array_filter($records,fn($r)=>strcasecmp($r['reference'],$search)===0);
 $matches=$exact?:array_filter($records,fn($r)=>mb_stripos(implode(' ',[$r['reference'],$r['destination'],$r['requester_name'],$r['personnel_name']??'',$r['driver_name']??'',$r['status'],$r['office_name']]),$search)!==false);
 if($lookup['start_datetime']!=='')$matches=array_filter($matches,fn($r)=>$r['end_datetime']>=str_replace('T',' ',$lookup['start_datetime']));
 if($lookup['end_datetime']!=='')$matches=array_filter($matches,fn($r)=>$r['start_datetime']<=str_replace('T',' ',$lookup['end_datetime']).':59');
 $items=[];
 foreach(array_slice(array_values($matches),0,10) as $r){
  $personnel=$r['kind']==='Personnel';
  $next=match($r['status']){'Draft'=>'This is a draft. Its requester needs to submit it for approval.','Returned for Correction'=>'The requester needs to correct and resubmit it.','Pending Supervisor','Pending Administrative Approval'=>'It is waiting for administrator review.','Approved'=>$personnel?'Approved; the assignment has not started yet.':'Approved; the vehicle has not been dispatched yet.','In Progress'=>'The assignment has started and is awaiting completion.','Dispatched'=>'The vehicle is out; its return has not been recorded.','Returned'=>'The vehicle return is recorded; the trip still needs completion.','Completed'=>'This request is complete.','Rejected'=>'This request was rejected.','Cancelled'=>'This request was cancelled.',default=>'Open the request for further details.'};
  $items[]=['reference'=>$r['reference'],'kind'=>$r['kind'],'status'=>$r['status'],'destination'=>$r['destination'],'start'=>$r['start_datetime'],'end'=>$r['end_datetime'],'assigned'=>$personnel?($r['personnel_name']?:'Not assigned'):trim(($r['model']??'Not assigned').' · '.($r['driver_name']??'Driver not assigned')),'actual_start'=>$personnel?($r['actual_start']??null):null,'actual_end'=>$personnel?($r['actual_end']??null):null,'next'=>$next,'url'=>secure_record_url('index.php?page='.($personnel?'personnel-request':'request').'&id='.$r['id'])];
 }
 $reply=!$items?'I couldn’t find a matching request you can access. Try its exact slip reference or another name.':(count($matches)===1?'Here’s the latest update for '.$items[0]['reference'].'. '.$items[0]['next']:'I found '.count($matches).' matching requests you can access. '.(count($matches)>10?'Showing the first 10. ':'').'Which slip would you like to discuss?');
 foreach($items as $item)$reply.="\n\n".$item['reference'].' — '.$item['status']."\nSchedule: ".$item['start'].' to '.$item['end']."\nAssigned: ".$item['assigned'].($item['actual_start']?"\nActual start: ".$item['actual_start']:'').($item['actual_end']?"\nCompleted: ".$item['actual_end']:'');
 return ['search'=>$search,'items'=>$items,'reply'=>$reply,'checked_at'=>date('Y-m-d H:i:s')];
}
