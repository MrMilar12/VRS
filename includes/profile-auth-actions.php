<?php
// Reauthentication and changes apply exclusively to the signed-in account.
$target='index.php?page=profile';$id=(int)$user['id'];
if($action==='factor_cancel'||$action==='factor_done'){
 unset($_SESSION['profile_enrollment'],$_SESSION['profile_recovery']);redirect($target);
}
auth_limit('factor-settings',(string)$id,5,900);
if(in_array($action,['factor_start','factor_disable'])){
 if(!password_verify(auth_input('password',200),$user['password_hash']))throw new RuntimeException('Your confirmation password is incorrect.');
}
if($action==='factor_start'){
 if(one('SELECT user_id FROM auth_factors WHERE user_id=?',[$id]))throw new RuntimeException('Your authenticator is already enabled.');
 $_SESSION['profile_enrollment']=['secret'=>auth_base32(random_bytes(20)),'expires'=>time()+600];
 unset($_SESSION['profile_recovery']);redirect($target);
}
if($action==='factor_enable'){
 $setup=$_SESSION['profile_enrollment']??[];
 if(($setup['expires']??0)<time()){unset($_SESSION['profile_enrollment']);throw new RuntimeException('Setup expired. Confirm your password to start again.');}
 $counter=auth_totp_counter($setup['secret'],trim(auth_input('code',24)));
 if($counter===null)throw new RuntimeException('The code is invalid or expired. Check your phone’s time and try a new code.');
 $codes=auth_recovery_codes();$encrypted=auth_encrypt($setup['secret']);
 lock_transaction();$transaction=true;one('SELECT id FROM users WHERE id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id]);
 if(one('SELECT user_id FROM auth_factors WHERE user_id=?',[$id]))throw new RuntimeException('Your authenticator is already enabled.');
 run('INSERT INTO auth_factors(user_id,secret_cipher,recovery_hashes,last_counter) VALUES(?,?,?,?)',[$id,$encrypted,json_encode(array_map('auth_recovery_hash',$codes)),$counter]);
 run('DELETE FROM auth_preferences WHERE user_id=?',[$id]);
 audit('Authenticator enabled','Enabled from account profile');finish_transaction(true);$transaction=false;
 unset($_SESSION['profile_enrollment']);$_SESSION['profile_recovery']=$codes;$_SESSION['auth_level']='mfa';
}elseif($action==='factor_disable'){
 lock_transaction();$transaction=true;one('SELECT id FROM users WHERE id=?'.($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':''),[$id]);
 run('DELETE FROM auth_factors WHERE user_id=?',[$id]);
 run('DELETE FROM auth_preferences WHERE user_id=?',[$id]);
 run('INSERT INTO auth_preferences(user_id,authenticator_disabled) VALUES(?,1)',[$id]);
 audit('Authenticator disabled','Disabled from account profile after password confirmation');finish_transaction(true);$transaction=false;
 unset($_SESSION['profile_enrollment'],$_SESSION['profile_recovery']);$_SESSION['auth_level']='password';
}else{throw new RuntimeException('Choose a valid authenticator action.');}
auth_clear_limit('factor-settings',(string)$id);session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));
flash($action==='factor_enable'?'Authenticator turned on. Save your new recovery codes.':'Authenticator turned off. You will sign in with your password.');redirect($target);
