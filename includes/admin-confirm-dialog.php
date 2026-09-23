<dialog
    id="admin-confirm-dialog"
    class="admin-confirm-dialog"
    aria-labelledby="admin-confirm-title"
    aria-describedby="admin-confirm-description"
>
    <form id="admin-confirm-form" class="stack">
        <div class="admin-confirm-heading">
            <span class="admin-confirm-icon" aria-hidden="true"><?= icon(
                'shield',
                26,
            ) ?></span
            ><button
                type="button"
                class="icon-button"
                data-admin-cancel
                aria-label="Close confirmation"
            >
                ×
            </button>
        </div>
        <div>
            <p class="admin-confirm-eyebrow">Administrator verification</p>
            <h2 id="admin-confirm-title">Confirm account action</h2>
        </div>
        <p id="admin-confirm-description"></p>
        <p class="admin-confirm-account" id="admin-confirm-account"></p>
        <label for="admin-confirm-password"
            >Your administrator password<input
                id="admin-confirm-password"
                type="password"
                required
                maxlength="200"
                autocomplete="current-password"
                placeholder="Enter your own password"
        /></label>
        <p class="field-hint">
            Use the password you sign in with, not the account holder’s password.
        </p>
        <div class="admin-confirm-actions">
            <button type="button" class="btn" data-admin-cancel>Cancel</button
            ><button type="submit" class="btn primary" id="admin-confirm-submit">Confirm</button>
        </div>
    </form>
</dialog>
<script src="assets/js/admin-confirm.js?v=<?= filemtime(
    __DIR__ . '/../assets/js/admin-confirm.js',
) ?>" defer></script>
