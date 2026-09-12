<?php
require __DIR__.'/../includes/url-security.php';
function auth_key(): string {return str_repeat('k',32);}
function verify_link(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function rejected_link(callable $call,string $message): void {try{$call();}catch(RuntimeException $e){verify_link(true,$message);return;}throw new RuntimeException($message);}
$user=['id'=>7];$token=record_link_token(123,'vehicles:edit');
verify_link(record_link_id($token,'vehicles:edit')===123,'Encrypted link round trip');
verify_link(record_link_token(123,'vehicles:edit')!==$token,'Fresh nonce for each link');
$changed=$token;$changed[24]=$changed[24]==='a'?'b':'a';rejected_link(fn()=>record_link_id($changed,'vehicles:edit'),'Tampered link rejected');
rejected_link(fn()=>record_link_id($token,'users:edit'),'Link cannot switch record types');
$user=['id'=>8];rejected_link(fn()=>record_link_id($token,'vehicles:edit'),'Link cannot be transferred to another account');$user=['id'=>7];
rejected_link(fn()=>record_link_id(['bad'],'vehicles:edit'),'Array parameter rejected');
$iv=random_bytes(12);$tag='';$key=hash_hmac('sha256','vrs-record-links-v1',auth_key(),true);$cipher=openssl_encrypt(json_encode(['id'=>123,'user'=>7,'expires'=>time()-1]),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'vehicles:edit');$expired='v1_'.rtrim(strtr(base64_encode($iv.$tag.$cipher),'+/','-_'),'=');
rejected_link(fn()=>record_link_id($expired,'vehicles:edit'),'Expired link rejected');
verify_link(str_contains(secure_record_url('index.php?page=vehicles&edit=123'),'edit=v1_'),'Generated edit URL hides numeric ID');
verify_link(secure_record_url('https://example.com/?id=123')==='https://example.com/?id=123','External links remain intact');
echo "All encrypted URL checks passed.\n";
