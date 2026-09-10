<?php
// Read-only answers: no model-generated SQL and no trip/personnel contact data.
function assistant_help(string $topic): string {
 return match($topic){
 'approval'=>'Only the Administrator can approve, reject, or return a vehicle request. Supervisors and Administrative Officers cannot approve. Submitted requests go directly to Pending Administrative Approval.',
 'booking'=>'Tell me the destination, departure and return date/time, vehicle type, passenger names, and purpose. Once complete, I will show the review form. Review it and select Submit request for approval. The Administrator assigns the vehicle and driver.',
 'editing'=>'You can edit your own Draft or Returned for Correction requisitions. Vehicle and driver records can be edited by Administrators and Administrative Officers. Users and offices can be edited only by the Administrator. On Vehicles, use Edit vehicle.',
 'authenticator'=>'Open My profile → Account security. Confirm your password to turn the authenticator off or begin setup. To turn it on, scan the QR code and verify a six-digit code, then save the recovery codes. An unused recovery code can also be used at sign-in.',
 'calendar'=>'Vehicle calendar shows approved trip schedules. Administrators see approved fleet trips; other roles see their own approved trips. Ask me for vehicle or driver availability to check the fleet without viewing private trip details.',
 'dispatch'=>'After Administrator approval, the Dispatcher or Administrator can release the vehicle, record its return and odometer readings, then complete the trip.',
 'profile'=>'Click your name/avatar or My profile in the sidebar to view your account, office, role, and authenticator settings. Contact your administrator to change your account details.',
 'availability'=>'Ask for available or unavailable vehicles or drivers, optionally by name, plate, vehicle type, and a departure/return time. Without times, I check right now. Results include assignment conflicts, maintenance, and registration/license validity. Availability can change; only Administrator approval reserves a vehicle.',
 default=>'I can help prepare vehicle requests, search available or unavailable vehicles and drivers, and explain approvals, editing, the calendar, dispatch, your profile, and authenticator settings. Try “Which vans are available now?” or “Who can approve my request?”.'};
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
 foreach(all('SELECT * FROM '.$kind.' ORDER BY '.($kind==='vehicles'?'model':'full_name')) as $row){
  $name=$kind==='vehicles'?$row['model'].' · '.$row['plate']:$row['full_name'];
  $haystack=$name.($kind==='vehicles'?' '.$row['type']:'');if($lookup['search']!==''&&mb_stripos($haystack,$lookup['search'])===false)continue;
  $reasons=[];$id=(int)$row['id'];
  if($kind==='vehicles'){
   if(!in_array($row['status'],['Available','Reserved']))$reasons[]=$row['status'];
   if($row['registration_expiry']<substr($end,0,10))$reasons[]='Registration expires before this period ends';
  }else{
   if($row['status']!=='Available')$reasons[]=$row['status'];
   if($row['license_expiry']<substr($end,0,10))$reasons[]='License expires before this period ends';
  }
  if(conflicts($kind==='vehicles'?$id:0,$kind==='drivers'?$id:0,$start,$end))$reasons[]=$kind==='vehicles'?'Scheduled trip or maintenance conflicts with this period (including turnaround)':'Scheduled trip conflicts with this period (including turnaround)';
  $ok=!$reasons;$ok?$available++:$unavailable++;
  if(($lookup['status']==='available'&&!$ok)||($lookup['status']==='unavailable'&&$ok))continue;
  $items[]=['name'=>$name,'available'=>$ok,'detail'=>$kind==='vehicles'?$row['type'].' · '.$row['capacity'].' seats':'Driver','reason'=>$ok?'No conflicts found for this period':implode('; ',array_unique($reasons))];
 }
 $count=count($items);$label=$live?'right now':date('M j, Y g:i A',strtotime($start)).' – '.date('M j, Y g:i A',strtotime($end));
 return ['kind'=>$kind,'lookup'=>$lookup,'live'=>$live,'checked_at'=>$now,'period'=>$label,'available_count'=>$available,'unavailable_count'=>$unavailable,'total'=>$count,'items'=>array_slice($items,0,50),'reply'=>($count?"Found $count matching $kind":"No matching $kind found").' for '.$label.'. '.($count>50?'Showing the first 50; narrow your search. ':'').'These are live availability checks, not reservations.'];
}
function assistant_resolve(array $result,array $current): array {
 $intent=$result['intent'];$result['availability']=null;
 if($intent!=='booking'){
  $result=array_replace($result,booking_validate($current));
  if(in_array($intent,['vehicles','drivers'])){$result['availability']=assistant_availability($intent,$result['lookup']);$result['reply']=$result['availability']['reply'];}
  else $result['reply']=assistant_help($result['topic']);
 }
 return $result;
}
