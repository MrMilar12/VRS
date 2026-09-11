<?php page_heading('Developer center','A fresh start for your workspace.'); ?>
<link rel="stylesheet" href="assets/css/developer.css?v=<?=filemtime(__DIR__.'/../assets/css/developer.css')?>">
<div class="developer-center" data-developer-center>
<section class="update-card">
 <div class="update-topline"><span class="update-eyebrow">WORKSPACE SOFTWARE</span><button class="btn" type="button" data-update-check>Check for updates</button></div>
 <div class="update-hero"><div class="update-symbol" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none"><path d="M24 32V12m-8 8 8-8 8 8M12 30v7h24v-7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h2>Better with every update.</h2><p class="developer-status" data-update-status role="status">Checking for a new version…</p><p class="update-description">Your workspace, kept up to date. You choose when to install.</p></div>
 <div class="update-versions"><div><span>Installed version</span><strong data-update-current>—</strong></div><span class="update-version-divider" aria-hidden="true">/</span><div><span>Latest version</span><strong data-update-latest>—</strong></div></div>
 <p class="update-release" data-update-summary></p>
 <div class="update-progress"><div class="update-progress-heading"><span data-update-phase>Ready when you are</span><strong data-update-percent>0%</strong></div><div class="update-track" data-update-progress role="progressbar" aria-label="Update steps completed" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span data-update-fill></span></div><div class="update-steps"><span>Download & validate</span><span>Review & install</span><span>Complete</span></div><p class="field-hint">Progress reflects completed steps.</p></div>
 <ul data-update-blockers class="developer-blockers"></ul>
 <div class="update-actions"><button class="btn primary" type="button" data-update-download disabled>Update now</button><p data-update-checked class="field-hint"></p></div>
 <details data-update-changes hidden><summary>Included in this update <span data-update-count></span></summary><ul data-update-files></ul></details>
 <form class="stack developer-update-form" data-update-form><?=csrf()?>
  <div data-update-confirm hidden><label>Administrator password<input type="password" name="password" autocomplete="current-password" required></label><p class="field-hint">Review the patch before installing. Your existing code is backed up; local settings, databases, and uploads are preserved.</p><button class="btn primary" type="submit" data-update-apply disabled>Install update</button></div>
  <button class="btn" type="button" data-update-rollback hidden>Restore previous version</button>
 </form>
</section>
</div>
<script src="assets/js/developer.js?v=<?=filemtime(__DIR__.'/../assets/js/developer.js')?>" defer></script>
