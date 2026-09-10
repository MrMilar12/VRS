<?php
// Always derive profile ownership from the authenticated session, never a URL ID.
$office=one('SELECT name FROM offices WHERE id=?',[$user['office_id']]);
$factor=one('SELECT recovery_hashes FROM auth_factors WHERE user_id=?',[$user['id']]);
$remaining=count(json_decode($factor['recovery_hashes']??'[]',true)?:[]);
page_heading('My profile','Your workspace identity and sign-in protection.');
?>
<section class="profile-card" aria-labelledby="profile-name">
 <div class="profile-intro"><span class="avatar profile-avatar" aria-hidden="true"><?=e(implode('',array_map(fn($s)=>substr($s,0,1),array_slice(explode(' ',$user['full_name']),0,2))))?></span><div><h2 id="profile-name"><?=e($user['full_name'])?></h2><p><?=e($user['position']?:'Workspace member')?></p></div><?=badge($user['status'])?></div>
 <dl class="profile-details">
 <?php foreach(['Email address'=>$user['email'],'Office'=>$office['name']??'—','Role'=>$user['role'],'Position'=>$user['position']?:'Not specified'] as $label=>$value):?><div><dt><?=e($label)?></dt><dd><?=e($value)?></dd></div><?php endforeach?>
 </dl>
 <p class="profile-note">Contact your administrator to update your account details or office assignment.</p>
</section>
<section class="profile-card" aria-labelledby="profile-security">
 <h2 id="profile-security">Account security</h2>
 <dl class="profile-details"><div><dt>Authenticator app</dt><dd><?=$factor?'On':'Off'?></dd></div><div><dt>Recovery codes remaining</dt><dd><?=$factor?$remaining.' of 10':'Not active'?></dd></div><div><dt>Session protection</dt><dd>Signs out after 30 minutes of inactivity</dd></div><div><dt>Maximum session length</dt><dd>8 hours</dd></div></dl>
 <?php $setup=$_SESSION['profile_enrollment']??null;if($setup&&$setup['expires']<time()){unset($_SESSION['profile_enrollment']);$setup=null;} ?>
 <?php if(!empty($_SESSION['profile_recovery'])):?>
 <div class="profile-auth-setup"><h3>Save your recovery codes</h3><p>Each code works once if you cannot access your authenticator app. Store them somewhere private.</p><ul class="profile-recovery-codes"><?php foreach($_SESSION['profile_recovery'] as $code):?><li><code data-recovery-code><?=e($code)?></code></li><?php endforeach?></ul><button type="button" class="btn" data-download-recovery hidden>Download recovery codes</button><form method="post" action="actions.php" class="stack"><?=csrf()?><button class="btn primary" name="action" value="factor_done">I saved my codes</button></form></div>
 <?php elseif($setup&&!$factor):$uri='otpauth://totp/'.rawurlencode('VRS:'.$user['email']).'?'.http_build_query(['secret'=>$setup['secret'],'issuer'=>'VRS','algorithm'=>'SHA1','digits'=>6,'period'=>30],'','&',PHP_QUERY_RFC3986);?>
 <div class="profile-auth-setup"><h3>Connect your authenticator</h3><p>Scan this QR code with your authenticator app, then enter its six-digit code to turn verification on.</p><div data-auth-qr="<?=e($uri)?>"></div><details><summary>Enter a setup key manually</summary><code data-setup-secret><?=e($setup['secret'])?></code></details><form method="post" action="actions.php" class="stack"><?=csrf()?><label>Authenticator code<input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"></label><button class="btn primary" name="action" value="factor_enable">Verify & turn on</button></form><form method="post" action="actions.php"><?=csrf()?><button class="btn" name="action" value="factor_cancel">Cancel setup</button></form></div><script src="assets/js/vendor/qrcodegen.js" defer></script>
 <?php else:?>
 <p class="profile-note"><?=$factor?'Your authenticator adds a verification step when you sign in. Turning it off also removes your current recovery codes.':'Turn on your authenticator to add a verification code when you sign in.'?></p>
 <form method="post" action="actions.php" class="stack profile-auth-form"><?=csrf()?><label>Confirm your password<input name="password" type="password" required autocomplete="current-password"></label><button class="btn <?=$factor?'danger':'primary'?>" name="action" value="<?=$factor?'factor_disable':'factor_start'?>"><?=$factor?'Turn off authenticator':'Turn on authenticator'?></button></form>
 <?php endif?>
</section>
<script src="assets/js/authentication.js" defer></script>
