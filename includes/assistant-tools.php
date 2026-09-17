<?php
require_once __DIR__.'/assistant-tracking.php';
// Read-only answers: no model-generated SQL and no trip/personnel contact data.
function assistant_help(string $topic): string {
 return match($topic){
 'personnel'=>'For staff assistance, open Personnel requisitions → New personnel requisition. Enter the required role, places, schedule, and purpose, then submit it. The Administrator can assign multiple personnel. This chat prepares vehicle drafts; use the personnel form for staff requests.',
 'tracking'=>'Use the tracking search in the header. Enter a slip reference or personnel name to find requests you can access, then open one to see its status, schedule, and actual progress.',
 'notifications'=>'Open the bell icon in the header. All updates and Unread filters help you find new messages. View details opens the related request; Mark all as read clears unread indicators.',
 'registration'=>'New users request an account from the sign-in page. They cannot sign in until an Administrator approves them under Account requests. Approval grants Requester access.',
 'reminders'=>'Overview shows unfinished work, today’s work, and upcoming tasks for vehicle and personnel requests. Future tasks move into today’s group on their scheduled day. Unfinished work stays visible on following days until resolved. Open request takes you to its details.',
 'approval'=>'Only the Administrator can approve, reject, or return a vehicle request. Supervisors and Administrative Officers cannot approve. Submitted requests go directly to Pending Administrative Approval.',
 'booking'=>'Tell me the destination, departure and return date/time, vehicle type, passenger names, and purpose. Once complete, I will show the review form. Review it and select Submit request for approval. The Administrator assigns the vehicle and driver.',
 'editing'=>'You can edit your own Draft or Returned for Correction requisitions. Vehicle and driver records can be edited by Administrators and Administrative Officers. Users and offices can be edited only by the Administrator. On Vehicles, use Edit vehicle.',
 'authenticator'=>'Open My profile → Account security. Confirm your password to turn the authenticator off or begin setup. To turn it on, scan the QR code and verify a six-digit code, then save the recovery codes. An unused recovery code can also be used at sign-in.',
 'calendar'=>'Vehicle calendar shows approved trip schedules. Administrators see approved fleet trips; other roles see their own approved trips. Ask me for vehicle or driver availability to check the fleet without viewing private trip details.',
 'dispatch'=>'After Administrator approval, the Dispatcher or Administrator can release the vehicle, record its return and odometer readings, then complete the trip.',
 'profile'=>'Click your name/avatar or My profile in the sidebar to view your account, office, role, and authenticator settings. Contact your administrator to change your account details.',
 'availability'=>'Ask for available or unavailable vehicles or drivers, optionally by name, plate, vehicle type, and a departure/return time. Without times, I check right now. Results include assignment conflicts, maintenance, and registration/license validity. Availability can change; only Administrator approval reserves a vehicle.',
 default=>'Hi! I’m your VPRS assistant. You can tell me about a trip a little at a time, or ask how the system works. I can help prepare vehicle requests, search available or unavailable vehicles and drivers, and explain approvals, editing, the calendar, dispatch, personnel requisitions, tracking, notifications, your profile, and authenticator settings. Try “Which vans are available now?” or “Who can approve my request?”.'};
}
function assistant_lookup_validate(array $raw): array {
 $out=[];foreach(['search'=>100,'start_datetime'=>16,'end_datetime'=>16] as $key=>$max){$value=$raw[$key]??'';if($value===null)$value='';if(!is_string($value)||mb_strlen($value)>$max)throw new RuntimeException('Please use a shorter availability search.');$out[$key]=trim($value);}
 $out['status']=$raw['status']??'all';if(!in_array($out['status'],['all','available','unavailable']))throw new RuntimeException('Please ask for available, unavailable, or all resources.');
 foreach(['start_datetime','end_datetime'] as $key)if($out[$key]!==''){$d=DateTime::createFromFormat('!Y-m-d\TH:i',$out[$key]);if(!$d||$d->format('Y-m-d\TH:i')!==$out[$key])throw new RuntimeException('Please specify a valid departure and return date/time for the availability search.');}
 return $out;
}
function assistant_availability(string $kind,array $lookup): array {
 if(!in_array($kind,['vehicles','drivers']))throw new RuntimeException('Unsupported availability search.');
 $lookup=assistant_lookup_validate($lookup);$now=date('Y-m-d H:i:s');
 if(($lookup['start_datetime']==='')!==($lookup['end_datetime']===''))return ['reply'=>'What departure and return date/time should I check? Please provide both.','items'=>[],'needs_dates'=>true];
 $live=$lookup['start_datetime']==='';$start=$live?$now:str_replace('T',' ',$lookup['start_datetime']).':00';$end=$live?date('Y-m-d H:i:s',time()+60):str_replace('T',' ',$lookup['end_datetime']).':00';
 if($end<=$start)return ['reply'=>'Please provide a return time after departure for the availability check.','items'=>[],'needs_dates'=>true];
 $items=[];$available=0;$unavailable=0;
 foreach(all($kind==='vehicles'?'SELECT * FROM vehicles ORDER BY model':"SELECT d.* FROM drivers d JOIN personnel p ON p.driver_id=d.id WHERE p.classification='Driver' ORDER BY d.full_name") as $row){
  $name=$kind==='vehicles'?$row['model'].' · '.$row['plate']:$row['full_name'];
  $haystack=$name.($kind==='vehicles'?' '.$row['type']:'');if($lookup['search']!==''&&mb_stripos($haystack,$lookup['search'])===false)continue;
  $reasons=[];$id=(int)$row['id'];
  if($kind==='vehicles'){
   if(!in_array($row['status'],['Available','Reserved']))$reasons[]=$row['status'];
   if($row['registration_expiry']<substr($end,0,10))$reasons[]='Registration expires before this period ends';
  }else{
   $reasons=driver_assignment_issues($row,['start_datetime'=>$start,'end_datetime'=>$end]);
  }
  if(conflicts($kind==='vehicles'?$id:0,$kind==='drivers'?$id:0,$start,$end))$reasons[]=$kind==='vehicles'?'Scheduled trip or maintenance conflicts with this period (including turnaround)':'Scheduled trip conflicts with this period (including turnaround)';
  $ok=!$reasons;$ok?$available++:$unavailable++;
  if(($lookup['status']==='available'&&!$ok)||($lookup['status']==='unavailable'&&$ok))continue;
  $items[]=['name'=>$name,'available'=>$ok,'detail'=>$kind==='vehicles'?$row['type'].' · '.$row['capacity'].' seats':'Driver','reason'=>$ok?'No conflicts found for this period':implode('; ',array_unique($reasons))];
 }
 $count=count($items);$label=$live?'right now':date('M j, Y g:i A',strtotime($start)).' – '.date('M j, Y g:i A',strtotime($end));
 return ['kind'=>$kind,'lookup'=>$lookup,'live'=>$live,'checked_at'=>$now,'period'=>$label,'available_count'=>$available,'unavailable_count'=>$unavailable,'total'=>$count,'items'=>array_slice($items,0,50),'reply'=>($count?"Found $count matching $kind":"No matching $kind found").' for '.$label.'. '.($count>50?'Showing the first 50; narrow your search. ':'').'These are live availability checks, not reservations.'];
}
function assistant_resolve(array $result,array $current,?string $trackingSearch=null): array {
 $intent=$result['intent'];$result['availability']=null;
 if($intent!=='booking'){
  $result=array_replace($result,booking_validate($current));
  if(in_array($intent,['vehicles','drivers'])){$result['availability']=assistant_availability($intent,$result['lookup']);$result['reply']=$result['availability']['reply'];}
  elseif($intent==='tracking'){$result['tracking']=assistant_track($result['lookup'],$trackingSearch??($_SESSION['booking_chat']['tracking_search']??''));$result['reply']=$result['tracking']['reply'];}
  elseif($intent==='system'){$result['reply']=assistant_help($result['topic']);if($result['topic']==='booking'&&!$result['ready'])$result['reply'].="\n\n".booking_followup($result);}
  elseif(trim($result['reply']??'')==='')$result['reply']=assistant_help('overview');
 }
 return $result;
}

// Common greetings and help remain usable without an external AI connection.
function assistant_local_reply(string $message,array $current): ?array {
 $text=mb_strtolower(trim($message));$topic=null;$reply=null;
 if(preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening|kumusta|kamusta|hello po|hi po)[!?. ]*$/u',$text))$reply='Hello! I can help plan a vehicle trip or answer questions about VPRS. What would you like help with?';
 elseif(preg_match('/^(thanks|thank you|salamat|salamat po)[!?. ]*$/u',$text))$reply='You’re welcome! You can ask another question or continue planning your trip.';
 elseif(in_array($text,['help','what can you do?','how do i use the booking assistant?','how does approval work?','how do i request personnel?','how do i track my request?','what appears on overview?'],true))$topic=match($text){'how does approval work?'=>'approval','how do i request personnel?'=>'personnel','how do i track my request?'=>'tracking','what appears on overview?'=>'reminders',default=>'overview'};
 if($reply===null&&$topic===null)return null;
 return ['intent'=>$topic?'system':'conversation','topic'=>$topic??'overview','lookup'=>[],'reply'=>$reply??assistant_help($topic),...booking_validate($current)];
}
