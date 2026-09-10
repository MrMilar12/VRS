<?php
require __DIR__.'/includes/bootstrap.php';
if($user)redirect('index.php');$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try{
 check_csrf();$email=strtolower(trim(auth_input('email',190)));$password=auth_input('password',200);
 auth_limit('password-ip',$_SERVER['REMOTE_ADDR']??'local',40,900);auth_limit('password-account',$email,5,900);
 $account=one("SELECT * FROM users WHERE email=? AND status='Active'",[$email]);
 $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
 if(strlen($password)>72||!password_verify($password,$account['password_hash']??$dummy)||!$account)throw new RuntimeException('Email or password is incorrect, or the account is not active.');
 session_regenerate_id(true);unset($_SESSION['user_id'],$_SESSION['auth_enrollment'],$_SESSION['auth_recovery']);
 $_SESSION['auth_pending']=['id'=>$account['id'],'credential'=>hash('sha256',$account['password_hash']),'expires'=>time()+600];$_SESSION['csrf']=bin2hex(random_bytes(32));
 if(auth_is_disabled((int)$account['id'])){auth_complete($account,false);redirect('index.php');}
 redirect('two-factor.php');
}catch(Throwable $e){$error=$e instanceof PDOException?'Sign in is temporarily unavailable.':$e->getMessage();}
$organization=setting('organization');
require __DIR__.'/includes/login-view.php';
