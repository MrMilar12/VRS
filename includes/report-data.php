<?php
$validDate=static function($s,$fallback){if(!is_string($s))return $fallback;$d=DateTime::createFromFormat('!Y-m-d',$s);return $d&&$d->format('Y-m-d')===$s?$s:$fallback;};
$from=$validDate($_GET['from']??'',date('Y-m-01'));$to=$validDate($_GET['to']??'',date('Y-m-t'));if($to<$from)[$from,$to]=[$to,$from];$office=(int)($_GET['office']??0);
$reportRows=array_values(array_filter(requests(),fn($r)=>substr($r['start_datetime'],0,10)<=$to&&substr($r['end_datetime'],0,10)>=$from&&(!$office||$r['office_id']==$office)));
foreach($reportRows as &$rr){$movement=one('SELECT * FROM vehicle_movements WHERE requisition_id=?',[$rr['id']]);$rr['distance']=$movement&&$movement['odometer_in']!==null?max(0,$movement['odometer_in']-$movement['odometer_out']):0;$rr['return_time']=$movement['return_time']??null;$rr['late']=($rr['return_time']&&$rr['return_time']>$rr['end_datetime'])||($rr['status']==='Dispatched'&&$rr['end_datetime']<date('Y-m-d H:i:s'));}unset($rr);
