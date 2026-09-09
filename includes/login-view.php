<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#203e32"><title>Sign in · VRS</title><link rel="stylesheet" href="assets/css/style.css?v=<?=filemtime(__DIR__.'/../assets/css/style.css')?>"><link rel="stylesheet" href="assets/css/login.css?v=<?=filemtime(__DIR__.'/../assets/css/login.css')?>"><script src="assets/js/login.js" defer></script></head>
<body class="auth-page"><main class="auth-layout">
<section class="auth-story" aria-labelledby="story-title">
<a class="auth-brand" href="index.php" aria-label="VRS home"><span class="auth-brand-mark"><?=icon('car',25)?></span><span>vrs<span class="auth-brand-caption">FLEET OPERATIONS</span></span></a>
<div class="auth-story-copy"><span class="auth-kicker"><i></i> A BETTER WAY TO MOVE</span><h1 id="story-title">Good journeys<br>start <em>here.</em></h1><p>A little less paperwork.<br>A lot more moving forward.</p></div>
<div class="auth-route" aria-hidden="true">
<svg viewBox="0 0 620 300" fill="none"><path class="map-contour" d="M-70 250C60 20 290 400 670 40M-70 225C60-5 290 375 670 15M-70 200C60-30 290 350 670-10M-70 275C60 45 290 425 670 65M-70 300C60 70 290 450 670 90"/><path class="map-road" d="M-35 233H145C205 233 205 98 270 98H365C435 98 435 226 510 226H665"/><path class="map-road-center" d="M-35 233H145C205 233 205 98 270 98H365C435 98 435 226 510 226H665"/><circle cx="110" cy="233" r="8" fill="#dae9a4"/><circle cx="510" cy="226" r="8" fill="#dae9a4"/><circle cx="510" cy="226" r="17" stroke="#dae9a4" stroke-opacity=".35"/></svg>
<div class="route-note"><span class="route-note-icon"><?=icon('check',16)?></span><div>Your next journey<small>All in one place.</small></div></div>
<div class="route-vehicle"><?=icon('car',35)?></div><span class="route-caption">FROM REQUEST TO THE ROAD</span>
</div>
<div class="auth-story-footer"><span>Request. Approve. Go.</span><span class="auth-footer-arrow"><?=icon('arrow',20)?></span></div>
</section>
<section class="auth-entry" aria-labelledby="login-title">
<div class="auth-entry-top"><span><?=icon('shield',14)?> Your workspace, connected.</span><span class="auth-edition">VRS / 01</span></div>
<div class="auth-form-wrap">
<div class="auth-office"><?=icon('office',16)?><span><?=e($organization)?></span></div>
<h2 id="login-title">Welcome back.</h2><p class="auth-description">Sign in to keep your day moving.</p>
<?php if($error):?><div class="auth-error" role="alert"><?=icon('shield',18)?><span><?=e($error)?></span></div><?php endif?>
<form method="post" class="auth-form"><?=csrf()?>
<label for="login-email">Email address</label><div class="auth-input"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 7 8 6 8-6"/></svg><input id="login-email" name="email" type="email" required autocomplete="username" value="<?=e($_POST['email']??'')?>" placeholder="you@your-office.gov.ph" spellcheck="false" autocapitalize="none"></div>
<label for="login-password">Password</label><div class="auth-input"><?=icon('shield',18)?><input id="login-password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password"><button type="button" class="password-toggle" aria-controls="login-password" aria-label="Show password" aria-pressed="false" hidden>Show</button></div>
<button class="auth-submit" type="submit"><span>Sign in to workspace</span><?=icon('arrow',19)?></button>
</form>
<p class="auth-help">Need access? Contact your workspace administrator.</p>
<?php if($config['demo']):?><div class="auth-demo"><div class="auth-divider"><span>JUST LOOKING AROUND?</span></div><form method="post"><?=csrf()?><input type="hidden" name="email" value="daniel@vrs.local"><input type="hidden" name="password" value="Demo@12345"><button class="auth-demo-button" type="submit"><?=icon('grid',17)?><span>Explore demo workspace</span><?=icon('arrow',16)?></button></form><details class="auth-demo-details"><summary>Demo accounts & workspace setup</summary><p>Sign in as requester, supervisor, admin, or dispatch<br>@vrs.local · Password: <code>Demo@12345</code></p><a href="setup.php">Set up your own workspace <?=icon('arrow',13)?></a></details></div><?php endif?>
</div>
<footer class="auth-entry-footer"><span>Vehicle Requisition & Scheduling</span><span>Built for better journeys.</span></footer>
</section></main></body></html>
