<?php
// Additive security tables work with existing SQLite and MySQL installations.
function auth_schema(PDO $db): void {
 $db->exec('CREATE TABLE IF NOT EXISTS auth_preferences (user_id INTEGER PRIMARY KEY, authenticator_disabled INTEGER NOT NULL DEFAULT 0)');
 $db->exec('CREATE TABLE IF NOT EXISTS auth_factors (user_id INTEGER PRIMARY KEY, secret_cipher TEXT NOT NULL, recovery_hashes TEXT NOT NULL, last_counter BIGINT NOT NULL DEFAULT -1)');
 $db->exec('CREATE TABLE IF NOT EXISTS auth_limits (bucket VARCHAR(64) PRIMARY KEY, attempts INTEGER NOT NULL, window_start BIGINT NOT NULL)');
}
function auth_input(string $name,int $max=255): string {
 $value=$_POST[$name]??'';
 if(!is_string($value)||mb_strlen($value)>$max)throw new RuntimeException('Enter a valid '.str_replace('_',' ',$name).'.');
 return $value;
}
function auth_password_policy(string $password): void {
 if(mb_strlen($password)<12||strlen($password)>72)throw new RuntimeException('Use a password of at least 12 characters and at most 72 bytes.');
 if(in_array(strtolower($password),['password1234','password12345','password123456','123456789012','qwerty123456']))throw new RuntimeException('Choose a less predictable password or a longer passphrase.');
}
function auth_limit(string $kind,string $identity,int $limit,int $seconds): void {
 global $pdo;$bucket=hash('sha256',$kind.'|'.$identity);$now=time();$cutoff=$now-$seconds;
 if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
  run('INSERT INTO auth_limits(bucket,attempts,window_start) VALUES(?,1,?) ON CONFLICT(bucket) DO UPDATE SET attempts=CASE WHEN window_start<=? THEN 1 ELSE attempts+1 END,window_start=CASE WHEN window_start<=? THEN ? ELSE window_start END',[$bucket,$now,$cutoff,$cutoff,$now]);
 }else{
  run('INSERT INTO auth_limits(bucket,attempts,window_start) VALUES(?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(window_start<=?,1,attempts+1),window_start=IF(window_start<=?,?,window_start)',[$bucket,$now,$cutoff,$cutoff,$now]);
 }
 if((int)one('SELECT attempts FROM auth_limits WHERE bucket=?',[$bucket])['attempts']>$limit)throw new RuntimeException('Too many attempts. Please try again later.');
}
function auth_clear_limit(string $kind,string $identity): void {run('DELETE FROM auth_limits WHERE bucket=?',[hash('sha256',$kind.'|'.$identity)]);}
function auth_base32(string $bytes): string {
 $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(unpack('C*',$bytes) as $byte)$bits.=str_pad(decbin($byte),8,'0',STR_PAD_LEFT);
 $result='';foreach(str_split($bits,5) as $chunk)$result.=$alphabet[bindec(str_pad($chunk,5,'0'))];return $result;
}
function auth_base32_decode(string $secret): string {
 $bits='';foreach(str_split($secret) as $char){$value=strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',$char);if($value===false)throw new RuntimeException('Invalid authenticator secret.');$bits.=str_pad(decbin($value),5,'0',STR_PAD_LEFT);}
 $result='';foreach(str_split($bits,8) as $chunk)if(strlen($chunk)===8)$result.=chr(bindec($chunk));return $result;
}
function auth_totp(string $secret,int $counter,int $digits=6): string {
 $hash=hash_hmac('sha1',pack('N2',intdiv($counter,4294967296),$counter%4294967296),auth_base32_decode($secret),true);
 $offset=ord($hash[19])&15;$number=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;
 return str_pad((string)($number%(10**$digits)),$digits,'0',STR_PAD_LEFT);
}
function auth_totp_counter(string $secret,string $code,int $last=-1,?int $now=null): ?int {
 if(!preg_match('/^\d{6}$/D',$code))return null;$counter=intdiv($now??time(),30);
 foreach([0,-1,1] as $delta){$candidate=$counter+$delta;if($candidate>$last&&hash_equals(auth_totp($secret,$candidate),$code))return $candidate;}return null;
}
function auth_key(): string {
 $path=__DIR__.'/../storage/auth.key';$file=@fopen($path,'c+b');
 if(!$file||!flock($file,LOCK_EX))throw new RuntimeException('Authenticator storage is unavailable. Contact your administrator.');
 try{$key=stream_get_contents($file);if($key===''){$key=random_bytes(32);if(fwrite($file,$key)!==32||!fflush($file))throw new RuntimeException('Unable to save authenticator key.');@chmod($path,0600);}if(strlen($key)!==32)throw new RuntimeException('Authenticator key is invalid. Contact your administrator.');return $key;}finally{flock($file,LOCK_UN);fclose($file);}
}
function auth_encrypt(string $secret): string {
 $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($secret,'aes-256-gcm',auth_key(),OPENSSL_RAW_DATA,$iv,$tag);
 if($cipher===false)throw new RuntimeException('Unable to protect authenticator credentials.');return base64_encode($iv.$tag.$cipher);
}
function auth_decrypt(string $encrypted): string {
 $data=base64_decode($encrypted,true);if($data===false||strlen($data)<29)throw new RuntimeException('Authenticator credentials are unavailable.');
 $secret=openssl_decrypt(substr($data,28),'aes-256-gcm',auth_key(),OPENSSL_RAW_DATA,substr($data,0,12),substr($data,12,16));
 if($secret===false)throw new RuntimeException('Authenticator credentials are unavailable. Contact your administrator.');return $secret;
}
function auth_recovery_codes(): array {$codes=[];for($i=0;$i<10;$i++)$codes[]=implode('-',str_split(strtoupper(bin2hex(random_bytes(10))),5));return $codes;}
function auth_recovery_hash(string $code): string {return hash('sha256',strtoupper(str_replace(['-',' '],'',$code)));}
function auth_pending_account(): ?array {
 $pending=$_SESSION['auth_pending']??null;
 if(!$pending||($pending['expires']??0)<time()) {unset($_SESSION['auth_pending'],$_SESSION['auth_enrollment'],$_SESSION['auth_recovery']);return null;}
 $account=one("SELECT * FROM users WHERE id=? AND status='Active'",[$pending['id']]);
 if(!$account||!hash_equals($pending['credential'],hash('sha256',$account['password_hash']))) {unset($_SESSION['auth_pending'],$_SESSION['auth_enrollment'],$_SESSION['auth_recovery']);return null;}
 return $account;
}
function auth_complete(array $account,bool $mfa=true): void {
 global $user;
 session_regenerate_id(true);$_SESSION=['database_identity'=>$_SESSION['database_identity'],'csrf'=>bin2hex(random_bytes(32)),'user_id'=>$account['id'],'auth_level'=>$mfa?'mfa':'password','auth_started'=>time(),'auth_seen'=>time(),'auth_credential'=>hash('sha256',$account['password_hash'])];
 $user=$account;auth_clear_limit('password-account',strtolower($account['email']));auth_clear_limit('factor',(string)$account['id']);audit('Signed in',$mfa?'Password and second factor verified':'Password verified; authenticator turned off');
}
function auth_verify_factor(int $id,string $code): bool {
 global $pdo;$transaction=false;
 try{
  lock_transaction();$transaction=true;
  $factor=one('SELECT * FROM auth_factors WHERE user_id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id]);
  if(!$factor){finish_transaction(false);return false;}
  $counter=auth_totp_counter(auth_decrypt($factor['secret_cipher']),$code,(int)$factor['last_counter']);
  if($counter!==null){run('UPDATE auth_factors SET last_counter=? WHERE user_id=?',[$counter,$id]);finish_transaction(true);return true;}
  $normalized=strtoupper(str_replace(['-',' '],'',$code));$hashes=json_decode($factor['recovery_hashes'],true,512,JSON_THROW_ON_ERROR);
  if(preg_match('/^[A-F0-9]{20}$/D',$normalized)){foreach($hashes as $i=>$hash)if(hash_equals($hash,auth_recovery_hash($code))){unset($hashes[$i]);run('UPDATE auth_factors SET recovery_hashes=? WHERE user_id=?',[json_encode(array_values($hashes)),$id]);finish_transaction(true);return true;}}
  finish_transaction(false);return false;
 }catch(Throwable $e){if($transaction&&($pdo->inTransaction()||$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'))finish_transaction(false);throw $e;}
}

function auth_session_valid(?array $account,array $session,?int $now=null,bool $allowPassword=false): bool {
 $now=$now??time();
 return $account&&(($session['auth_level']??'')==='mfa'||($allowPassword&&($session['auth_level']??'')==='password'))&&$now-($session['auth_seen']??0)<=1800&&$now-($session['auth_started']??0)<=28800&&hash_equals($session['auth_credential']??'',hash('sha256',$account['password_hash']));
}

function auth_is_disabled(int $id): bool {
 return (int)(one('SELECT authenticator_disabled FROM auth_preferences WHERE user_id=?',[$id])['authenticator_disabled']??0)===1;
}
