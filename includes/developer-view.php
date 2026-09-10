<?php
page_heading('Developer center','Keep your workspace up to date with your GitHub repository.');
?>
<div class="developer-center" data-developer-center>
<section class="panel"><div class="panel-heading"><div><h2>Software updates</h2><p><?=e($config['update_repository']??'MrMilar12/VRS')?> · <?=e($config['update_branch']??'main')?></p></div><button class="btn primary" type="button" data-update-check>Check for updates</button></div>
<div class="form-body stack"><p class="developer-status" data-update-status role="status">Checking GitHub for updates…</p><p class="field-hint">Checks automatically every minute while this page is open. Updates install only when you choose Apply update.</p>
<dl class="profile-details"><div><dt>Installed version</dt><dd data-update-current>—</dd></div><div><dt>Latest version</dt><dd data-update-latest>—</dd></div></dl>
<p data-update-summary></p><p class="field-hint" data-update-checked></p><ul data-update-blockers class="developer-blockers"></ul>
<details data-update-changes hidden><summary>Files in this update <span data-update-count></span></summary><ul data-update-files></ul></details>
<form class="stack developer-update-form" data-update-form><?=csrf()?><label>Confirm administrator password<input type="password" name="password" autocomplete="current-password" required></label><p class="field-hint">Applying replaces committed application files. Local configuration, databases, authenticator keys, and uploads are protected. Database migration scripts are not run automatically.</p><div class="button-row"><button class="btn primary" type="submit" data-update-apply disabled>Apply update</button><button class="btn" type="button" data-update-rollback hidden>Roll back previous update</button></div></form>
</div></section>
<section class="panel"><div class="panel-heading"><h2>Publishing an update</h2></div><div class="form-body stack"><p>Commit your changes and push them to the configured GitHub branch. This page will display the new version and the files it changes.</p><p>Before installation, the updater checks the branch, local changes, protected paths, and PHP syntax. A Git backup reference is saved so the previous code can be restored.</p><p class="field-hint">The server account needs Git, PHP CLI, GitHub access, and write permission to the checkout and storage. Keep environment-specific settings in config/local.php.</p></div></section>
</div>
<script src="assets/js/developer.js?v=<?=filemtime(__DIR__.'/../assets/js/developer.js')?>" defer></script>
