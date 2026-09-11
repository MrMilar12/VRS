<?php
/** Encrypted record references supplement (never replace) record authorization. */
function record_link_token(int $id,string $scope): string {
 global $user;if(!$user||$id<1)throw new RuntimeException('Cannot create this record link.');
 $iv=random_bytes(12);$tag='';$key=hash_hmac('sha256','vrs-record-links-v1',auth_key(),true);
 $payload=json_encode(['id'=>$id,'user'=>(int)$user['id'],'expires'=>time()+604800],JSON_THROW_ON_ERROR);
 $cipher=openssl_encrypt($payload,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,$scope,16);
 if($cipher===false)throw new RuntimeException('Unable to protect record link.');
 return 'v1_'.rtrim(strtr(base64_encode($iv.$tag.$cipher),'+/','-_'),'=');
}
function record_link_id(mixed $value,string $scope): int {
 global $user;
 // Existing bookmarks remain subject to the same page and record authorization.
 if(is_string($value)&&ctype_digit($value))return (int)$value;
 if(!is_string($value)||strlen($value)>1024||!preg_match('/^v1_[A-Za-z0-9_-]+$/D',$value)||!$user)throw new RuntimeException('Invalid record link. Open the record from its list.');
 $raw=base64_decode(strtr(substr($value,3),'-_','+/'),true);
 if($raw===false||strlen($raw)<29)throw new RuntimeException('Invalid record link.');
 $key=hash_hmac('sha256','vrs-record-links-v1',auth_key(),true);
 $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$scope);
 $data=$plain===false?null:json_decode($plain,true);
 if(!is_array($data)||($data['user']??null)!==(int)$user['id']||($data['expires']??0)<time()||!is_int($data['id']??null)||$data['id']<1)throw new RuntimeException('This record link is invalid or expired. Open the record from its list.');
 return $data['id'];
}
function secure_record_url(string $url): string {
 $parts=parse_url($url);if($parts===false||isset($parts['host'])||!in_array($parts['path']??'',['index.php','../index.php'],true))return $url;
 parse_str($parts['query']??'',$query);$page=$query['page']??'';if(!is_string($page))return $url;
 foreach(['id','edit'] as $param)if(isset($query[$param])&&is_scalar($query[$param])&&ctype_digit((string)$query[$param])&&(int)$query[$param]>0)$query[$param]=record_link_token((int)$query[$param],$page.':'.$param);
 return $parts['path'].($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
}
