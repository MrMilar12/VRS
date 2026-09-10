<?php
require __DIR__.'/includes/bootstrap.php';if($user)redirect('index.php');require __DIR__.'/includes/public-auth-layout.php';
$error='';$submitted=false;
if($_SERVER['REQUEST_METHOD']==='POST')try{
 check_csrf();auth_limit('registration-ip',$_SERVER['REMOTE_ADDR']??'local',5,3600);
 $name=trim(auth_input('full_name',160));$email=strtolower(trim(auth_input('email',190)));$position=trim(auth_input('position',160));$office=filter_var(auth_input('office_id',20),FILTER_VALIDATE_INT);
 if($name===''||$position===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter your name, designation, and a valid email address.');
 if(!$office||!one("SELECT id FROM offices WHERE id=? AND status='Active'",[$office]))throw new RuntimeException('Select an active office.');
 auth_limit('registration-email',$email,3,3600);
 $password=auth_input('password',200);auth_password_policy($password);
 if(!hash_equals($password,auth_input('confirm_password',200)))throw new RuntimeException('The passwords do not match.');
 $hash=password_hash($password,PASSWORD_DEFAULT);
 // A fixed role and pending status prevent privilege selection through forged fields.
 if(!one('SELECT id FROM users WHERE email=?',[$email])){
  try{run('INSERT INTO users(full_name,email,password_hash,role,office_id,position,status) VALUES(?,?,?,?,?,?,?)',[$name,$email,$hash,'Requester',$office,$position,'Pending']);$id=(int)$pdo->lastInsertId();audit('Registration requested','Pending requester account #'.$id);
   foreach(all("SELECT id FROM users WHERE role='Administrator' AND status='Active'") as $admin)run('INSERT INTO notifications(user_id,message,created_at) VALUES(?,?,?)',[$admin['id'],'A new account is awaiting review in Users & roles.',date('Y-m-d H:i:s')]);
  }catch(PDOException $e){if(!one('SELECT id FROM users WHERE email=?',[$email]))throw $e;}
 }
 $submitted=true;$_POST=[];
}catch(Throwable $e){$error=$e instanceof PDOException?'Registration is temporarily unavailable. Please try again later.':$e->getMessage();}
public_auth_start($submitted?'Request received':'Create an account',$submitted?'An administrator must approve new accounts before sign-in.':'Request access to your organization’s vehicle workspace.');
if($submitted):?><div class="alert success" role="status">If this email is eligible, your request has been recorded. Contact your workspace administrator to confirm approval. Existing accounts are unchanged.</div><p class="muted">After approval, sign in with your password and set up an authenticator app. You will receive one-time recovery codes during setup.</p><?php else:?>
<?php if($error):?><div class="auth-error" role="alert"><?=e($error)?></div><?php endif?>
<form method="post" class="stack auth-registration"><?=csrf()?><label>Full name<input name="full_name" required maxlength="160" autocomplete="name" value="<?=e(is_string($_POST['full_name']??null)?$_POST['full_name']:'')?>"></label><label>Email address<input type="email" name="email" required maxlength="190" autocomplete="username" value="<?=e(is_string($_POST['email']??null)?$_POST['email']:'')?>"></label><label>Office / division<select name="office_id" required><option value="">Select your office</option><?php foreach(all("SELECT id,name FROM offices WHERE status='Active' ORDER BY name") as $office):?><option value="<?=$office['id']?>" <?=($_POST['office_id']??'')==$office['id']?'selected':''?>><?=e($office['name'])?></option><?php endforeach?></select></label><label>Designation / position<input name="position" required maxlength="160" autocomplete="organization-title" value="<?=e(is_string($_POST['position']??null)?$_POST['position']:'')?>"></label><label>Password<span class="field-hint">Use at least 12 characters. A long, unique passphrase works well.</span><input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></label><label>Confirm password<input type="password" name="confirm_password" required minlength="12" maxlength="72" autocomplete="new-password"></label><p class="field-hint">New accounts receive Requester access after administrator review. An authenticator app is required at sign-in.</p><button class="auth-submit" type="submit">Request account <?=icon('arrow',18)?></button></form><?php endif;public_auth_end();
