<?php
require __DIR__.'/includes/bootstrap.php';
if($user)redirect('index.php');$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try{
 check_csrf();$email=strtolower(field('email',190));$identity=hash('sha256',($_SERVER['REMOTE_ADDR']??'local').'|'.$email);$since=date('Y-m-d H:i:s',time()-900);
 if((int)one('SELECT COUNT(*) n FROM login_attempts WHERE identity_hash=? AND attempted_at>?',[$identity,$since])['n']>=5)throw new RuntimeException('Too many attempts. Try again in 15 minutes.');
 run('INSERT INTO login_attempts(identity_hash,attempted_at) VALUES(?,?)',[$identity,date('Y-m-d H:i:s')]);
 $account=one("SELECT * FROM users WHERE email=? AND status='Active'",[$email]);
 if(!$account||!password_verify((string)($_POST['password']??''),$account['password_hash']))throw new RuntimeException('Email or password is incorrect.');
 session_regenerate_id(true);$_SESSION['user_id']=$account['id'];$_SESSION['csrf']=bin2hex(random_bytes(32));$user=$account;run('DELETE FROM login_attempts WHERE identity_hash=?',[$identity]);audit('Signed in');redirect('index.php');
}catch(Throwable $e){$error=$e instanceof PDOException?'Sign in is temporarily unavailable.':$e->getMessage();}
$organization=setting('organization');
require __DIR__.'/includes/login-view.php';
