<?php
require __DIR__.'/../includes/functions.php';require __DIR__.'/../includes/auth.php';
function check_auth(bool $ok,string $label): void {if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";}
$secret=auth_base32('12345678901234567890');
foreach([59=>'94287082',1111111109=>'07081804',1111111111=>'14050471',1234567890=>'89005924',2000000000=>'69279037',20000000000=>'65353130'] as $at=>$expected)check_auth(auth_totp($secret,intdiv($at,30),8)===$expected,'RFC 6238 SHA-1 vector '.$at);
check_auth(auth_base32_decode($secret)==='12345678901234567890','Base32 round trip');
$counter=intdiv(time(),30);$code=auth_totp($secret,$counter);
check_auth(auth_totp_counter($secret,$code,$counter-1)===$counter,'Current authenticator code accepted');
check_auth(auth_totp_counter($secret,$code,$counter)===null,'Used authenticator counter rejected');
check_auth(auth_totp_counter($secret,'000000',-1,59)===null,'Incorrect authenticator code rejected');
$codes=auth_recovery_codes();check_auth(count(array_unique($codes))===10,'Ten distinct random recovery codes');
check_auth(auth_recovery_hash(strtolower($codes[0]))===auth_recovery_hash($codes[0]),'Recovery-code normalization');
$account=['password_hash'=>'credential'];$session=['auth_level'=>'mfa','auth_seen'=>100000,'auth_started'=>100000,'auth_credential'=>hash('sha256','credential')];
check_auth(auth_session_valid($account,$session,100001),'Fully verified session accepted');
check_auth(!auth_session_valid($account,array_replace($session,['auth_level'=>'password']),100001),'Password-only session rejected');
check_auth(!auth_session_valid($account,$session,101801),'Idle session expires after thirty minutes');
check_auth(!auth_session_valid($account,array_replace($session,['auth_seen'=>130000]),130000),'Absolute session lifetime enforced');
check_auth(!auth_session_valid(['password_hash'=>'changed'],$session,100001),'Password changes invalidate existing sessions');
check_auth(!auth_session_valid(null,$session,100001),'Inactive or missing account cannot retain access');
foreach(['short','password1234',str_repeat('a',73)] as $password){$rejected=false;try{auth_password_policy($password);}catch(RuntimeException $e){$rejected=true;}check_auth($rejected,'Weak or overlong password rejected');}
auth_password_policy('Long unique phrase 2026!');
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);auth_schema($pdo);auth_schema($pdo);
for($i=0;$i<3;$i++)auth_limit('test','account',3,900);
$rejected=false;try{auth_limit('test','account',3,900);}catch(RuntimeException $e){$rejected=true;}check_auth($rejected,'Persistent rate limit rejects excess attempts');
auth_clear_limit('test','account');auth_limit('test','account',3,900);check_auth(true,'Successful authentication can clear an account limit');
echo "All authentication unit checks passed.\n";
