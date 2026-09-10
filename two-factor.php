<?php
require __DIR__.'/includes/bootstrap.php';if($user)redirect('index.php');require __DIR__.'/includes/public-auth-layout.php';
$account=auth_pending_account();if(!$account)redirect('login.php');
if(auth_is_disabled((int)$account['id'])){unset($_SESSION['auth_pending'],$_SESSION['auth_enrollment'],$_SESSION['auth_recovery']);redirect('login.php');}
$error='';$factor=one('SELECT * FROM auth_factors WHERE user_id=?',[$account['id']]);
if(!$factor&&empty($_SESSION['auth_enrollment']))$_SESSION['auth_enrollment']=auth_base32(random_bytes(20));
if($_SERVER['REQUEST_METHOD']==='POST')try{
 check_csrf();$action=auth_input('action',30);
 if($action==='cancel'){unset($_SESSION['auth_pending'],$_SESSION['auth_enrollment'],$_SESSION['auth_recovery']);$_SESSION['csrf']=bin2hex(random_bytes(32));redirect('login.php');}
 if($action==='finish'&&!empty($_SESSION['auth_pending']['verified'])){auth_complete($account);redirect('index.php');}
 auth_limit('factor',(string)$account['id'],5,900);auth_limit('factor-ip',$_SERVER['REMOTE_ADDR']??'local',40,900);
 $code=trim(auth_input('code',80));
 if(!$factor&&$action==='enroll'){
  $counter=auth_totp_counter($_SESSION['auth_enrollment'],$code);
  if($counter===null)throw new RuntimeException('The code is invalid or expired. Check your authenticator and try again.');
  $codes=auth_recovery_codes();$encrypted=auth_encrypt($_SESSION['auth_enrollment']);
  run('INSERT INTO auth_factors(user_id,secret_cipher,recovery_hashes,last_counter) VALUES(?,?,?,?)',[$account['id'],$encrypted,json_encode(array_map('auth_recovery_hash',$codes)),$counter]);
  $_SESSION['auth_pending']['verified']=true;$_SESSION['auth_recovery']=$codes;unset($_SESSION['auth_enrollment']);
  run('INSERT INTO audit_logs(user_id,action,details,created_at) VALUES(?,?,?,?)',[$account['id'],'Authenticator enrolled','Second factor verified; recovery codes issued',date('Y-m-d H:i:s')]);
  redirect('two-factor.php');
 }
 if($factor&&$action==='verify'){
  if(!auth_verify_factor((int)$account['id'],$code))throw new RuntimeException('The code is invalid, expired, or already used. Wait for a new code or use a recovery code.');
  auth_complete($account);redirect('index.php');
 }
 throw new RuntimeException('Refresh the page and try again.');
}catch(Throwable $e){$error=$e instanceof PDOException?'Verification is temporarily unavailable. Please sign in again.':$e->getMessage();}
$recovery=!empty($_SESSION['auth_pending']['verified'])&&!empty($_SESSION['auth_recovery']);
public_auth_start($recovery?'Save your recovery codes':($factor?'Verify your sign-in':'Set up two-step verification'),$recovery?'Store these codes somewhere safe before continuing.':($factor?'Enter the code from your authenticator app, or one unused recovery code.':'Add VRS to your authenticator app, then enter its six-digit code.'));
if($error):?><div class="auth-error" role="alert"><?=e($error)?></div><?php endif?>
<?php if($recovery):?><p class="muted">Each code works once in place of your authenticator. Keep them separate from your password. They will not be displayed again after you continue.</p><ul class="recovery-codes"><?php foreach($_SESSION['auth_recovery'] as $code):?><li><code data-recovery-code><?=e($code)?></code></li><?php endforeach?></ul><button type="button" class="btn" data-download-recovery hidden>Download recovery codes</button><form method="post" class="stack auth-registration"><?=csrf()?><input type="hidden" name="action" value="finish"><button class="auth-submit" type="submit">I saved my codes — continue <?=icon('arrow',18)?></button></form>
<?php else:?>
<?php if(!$factor):$secret=$_SESSION['auth_enrollment'];$uri='otpauth://totp/'.rawurlencode('VRS:'.$account['email']).'?'.http_build_query(['secret'=>$secret,'issuer'=>'VRS','algorithm'=>'SHA1','digits'=>6,'period'=>30],'','&',PHP_QUERY_RFC3986);?><div class="authenticator-setup"><div data-auth-qr="<?=e($uri)?>" aria-label="Authenticator setup QR code"></div><details><summary>Enter a setup key manually</summary><code data-setup-secret><?=e($secret)?></code><p class="field-hint">Account: <?=e($account['email'])?><br>Time-based · 6 digits · 30 seconds</p></details></div><script src="assets/js/vendor/qrcodegen.js" defer></script><?php endif?>
<form method="post" class="stack auth-registration"><?=csrf()?><input type="hidden" name="action" value="<?=$factor?'verify':'enroll'?>"><label><?=$factor?'Authenticator or recovery code':'Authenticator code'?><input name="code" required maxlength="24" autocomplete="one-time-code" <?=$factor?'':'inputmode="numeric" pattern="[0-9]{6}"'?> spellcheck="false" autocapitalize="characters" autofocus></label><button class="auth-submit" type="submit">Verify and continue <?=icon('arrow',18)?></button></form><?php endif?>
<form method="post" class="auth-cancel"><?=csrf()?><input type="hidden" name="action" value="cancel"><button class="btn" type="submit">Cancel sign-in</button></form><script src="assets/js/authentication.js" defer></script><?php public_auth_end();
