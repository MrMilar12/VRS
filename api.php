<?php
// Keep hosted PHP warnings/fatal errors from corrupting JSON responses.
ini_set('display_errors','0');
$apiRequestId=bin2hex(random_bytes(8));
header('X-VRS-Request-ID: '.$apiRequestId);
$apiBufferLevel=ob_get_level();
ob_start(static function(string $body,int $phase)use($apiRequestId): string {
 if($phase&PHP_OUTPUT_HANDLER_CLEAN)return '';
 json_decode($body);if(json_last_error()===JSON_ERROR_NONE)return $body;
 error_log('VRS API '.$apiRequestId.': request ended with a non-JSON response.');
 if(!headers_sent()){if(http_response_code()<400)http_response_code(500);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}
 return json_encode(['error'=>'The server could not finish the API request. Check the hosting PHP error log. Reference: '.$apiRequestId]);
});
function api_bootstrap_failure(string $reference,int $bufferLevel): never {
 while(ob_get_level()>$bufferLevel)ob_end_clean();
 if(!headers_sent()){header_remove('Location');http_response_code(500);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');}
 echo json_encode(['error'=>'The server could not start the API. Check the hosting PHP error log. Reference: '.$reference]);exit;
}
register_shutdown_function(static function()use($apiRequestId,$apiBufferLevel){
 $error=error_get_last();if($error&&in_array($error['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true)){
  error_log('VRS API '.$apiRequestId.': '.$error['message'].' in '.$error['file'].':'.$error['line']);api_bootstrap_failure($apiRequestId,$apiBufferLevel);
 }
});
try{require __DIR__.'/includes/bootstrap.php';}catch(Throwable $e){error_log('VRS API '.$apiRequestId.': '.$e->getMessage());api_bootstrap_failure($apiRequestId,$apiBufferLevel);}
header('Content-Type: application/json; charset=utf-8');
if(!$user){http_response_code(401);echo json_encode(['error'=>'Sign in to continue.']);exit;}
try{
 $action=$_GET['action']??'calendar';
 if($action==='updates'){require __DIR__.'/includes/developer-api.php';}
 elseif($action==='assistant'){require __DIR__.'/includes/booking-api.php';}
 elseif($action==='calendar'){
  $rows=calendar_requests();$resources=calendar_resources($rows);
  $events=[];foreach($rows as $r){$events[]=['id'=>$r['id'],'url'=>secure_record_url('index.php?page=request&id='.$r['id']),'reference'=>$r['reference'],'title'=>$r['destination'],'purpose'=>$r['purpose'],'start'=>$r['start_datetime'],'end'=>$r['end_datetime'],'status'=>$r['status'],'vehicle_id'=>$r['vehicle_id'],'vehicle'=>$r['model']??'Awaiting assignment','plate'=>$r['plate']??'—','type'=>$r['vehicle_type'],'office_id'=>$r['office_id'],'office'=>$r['office_name'],'office_code'=>$r['office_code'],'requester'=>$r['requester_name'],'driver_id'=>$r['driver_id'],'driver'=>$r['driver_name']??'Awaiting assignment'];}
  echo json_encode(['events'=>$events,'vehicles'=>$resources['vehicles']],JSON_THROW_ON_ERROR);
 }elseif($action==='availability'){
  require_role('Administrator','Administrative Officer');$r=get_request((int)($_GET['id']??0));$vehicle=(int)($_GET['vehicle_id']??0);$driver=(int)($_GET['driver_id']??0);$v=one('SELECT * FROM vehicles WHERE id=?',[$vehicle]);$d=one('SELECT * FROM drivers WHERE id=?',[$driver]);if(!$v||!$d)throw new RuntimeException('Select a vehicle and a driver first.');$scheduleIssues=conflicts($vehicle,$driver,$r['start_datetime'],$r['end_datetime'],$r['id']);$blockingIssues=[...vehicle_assignment_issues($v,$r),...driver_assignment_issues($d,$r)];$issues=[...$scheduleIssues,...$blockingIssues];echo json_encode(['available'=>!$issues,'overridable'=>is_role('Administrator')&&!$blockingIssues&&(bool)$scheduleIssues,'message'=>$issues?implode(' ',$issues):'Vehicle and driver are available, including the '.setting('turnaround_minutes').' minute turnaround buffer.']);
 }else{http_response_code(404);echo json_encode(['error'=>'Unknown endpoint.']);}
}catch(Throwable $e){http_response_code(400);echo json_encode(['error'=>$e instanceof PDOException?'Unable to load records.':$e->getMessage()]);}
