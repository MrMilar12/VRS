<?php
require __DIR__.'/../includes/booking-assistant.php';
function check_booking(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label."\n";}
$config=['openai_model'=>'gpt-4o-mini','timezone'=>'Asia/Manila','openai_api_key'=>''];
$draft=['destination'=>'Baler','start_datetime'=>'2026-12-10T08:00','end_datetime'=>'2026-12-10T17:00','vehicle_type'=>'Van','passengers'=>'Ana Cruz, Ben Reyes','purpose'=>'Planning meeting','preferred_driver'=>'','fuel_remarks'=>'','fuel_allocation'=>false,'fuel_quantity'=>0];
check_booking(booking_validate($draft)['ready'],'Complete request is ready for human review');
$unknown=booking_validate([]);check_booking(!$unknown['ready']&&count($unknown['missing'])===6,'Missing information is not fabricated');
check_booking(!booking_validate(array_replace($draft,['end_datetime'=>'2026-12-10T07:00']))['ready'],'Reversed travel times require correction');
foreach([['vehicle_type'=>'Helicopter'],['start_datetime'=>'2026-02-30T08:00'],['destination'=>['bad']],['fuel_quantity'=>-1],['purpose'=>str_repeat('x',5001)]] as $bad){$caught=false;try{booking_validate(array_replace($draft,$bad));}catch(RuntimeException $e){$caught=true;}check_booking($caught,'Invalid model output is rejected');}
$result=booking_validate([...$draft,'status'=>'Approved','requester_id'=>99,'vehicle_id'=>1]);check_booking(!isset($result['draft']['status'],$result['draft']['requester_id'],$result['draft']['vehicle_id']),'AI output cannot assign vehicles or approval authority');
$payload=booking_payload([['role'=>'user','content'=>'Book a van']],$draft);check_booking($payload['store']===false&&$payload['text']['format']['strict']===true&&!isset($payload['tools']),'AI request uses strict output with no execution tools');
$response=['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode(['reply'=>'Review your trip.','draft'=>$draft])]]]]];
check_booking(booking_parse_response($response)['ready'],'Structured Responses API output is parsed');
foreach([['status'=>'incomplete'],['status'=>'completed','output'=>[['content'=>[['type'=>'refusal']]]]],['status'=>'completed','output'=>[['content'=>[['type'=>'output_text','text'=>'broken json']]]]]] as $bad){$caught=false;try{booking_parse_response($bad);}catch(RuntimeException $e){$caught=true;}check_booking($caught,'Incomplete, refused or malformed responses fail safely');}
$caught=false;try{booking_respond([],[]);}catch(RuntimeException $e){$caught=str_contains($e->getMessage(),'not connected');}check_booking($caught,'Missing API configuration is explicit');
$config['booking_ai_provider']='ollama';$config['ollama_url']='http://127.0.0.1:11434';$config['ollama_model']='llama3.2:latest';
check_booking(booking_configured(),'Ollama does not require an OpenAI key');
$local=booking_ollama_payload([['role'=>'user','content'=>'Book a van']],$draft);
check_booking($local['model']==='llama3.2:latest'&&$local['stream']===false&&isset($local['format']['properties']['draft'])&&$local['messages'][0]['role']==='system','Ollama uses native chat and structured output');
check_booking(!isset($local['tools'],$local['store'],$local['input']),'Ollama payload excludes OpenAI-specific parameters');
check_booking(booking_parse_ollama_response(['done'=>true,'message'=>['content'=>json_encode(['reply'=>'Review your trip.','draft'=>$draft])]])['ready'],'Ollama output uses the same trip validation');
foreach([['done'=>false],['done'=>true,'done_reason'=>'length','message'=>['content'=>'{}']],['done'=>true,'message'=>['content'=>'not JSON']]] as $bad){$caught=false;try{booking_parse_ollama_response($bad);}catch(RuntimeException $e){$caught=true;}check_booking($caught,'Invalid or truncated Ollama reply is rejected');}

$claim=booking_parse_ollama_response(['done'=>true,'message'=>['content'=>json_encode(['reply'=>'Request submitted and approved','draft'=>$draft])]]);
check_booking(str_contains($claim['reply'],'Nothing has been submitted yet.')&&!str_contains($claim['reply'],'Request submitted and approved'),'Model cannot claim a booking was submitted or approved');
$config['ollama_model']='gpt-oss:120b-cloud';
$cloud=booking_ollama_payload([['role'=>'user','content'=>'A van to Baler']],[]);
check_booking(booking_ollama_cloud()&&!isset($cloud['format'])&&!isset($cloud['options'])&&str_contains($cloud['messages'][0]['content'],'Return only JSON'),'Cloud payload omits unsupported structured-output parameters and retains schema instructions');
check_booking($cloud['model']==='gpt-oss:120b-cloud'&&$cloud['stream']===false,'Cloud model uses local Ollama forwarding');

$config['ollama_url']='https://ollama.com';$config['ollama_api_key']='';
check_booking(!booking_configured(),'Direct cloud requires its own API key');
$config['ollama_api_key']='fixture-cloud-key';
check_booking(booking_configured()&&booking_ollama_direct(),'Direct cloud recognizes configured credentials');
$direct=booking_ollama_payload([['role'=>'user','content'=>'Book a van']],[]);
check_booking($direct['model']==='gpt-oss:120b'&&!isset($direct['format']),'Direct cloud normalizes local cloud model suffix');
check_booking(!str_contains(json_encode($direct),'fixture-cloud-key'),'API key excluded from model prompt');
