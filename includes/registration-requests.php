<?php
require_role('Administrator');
page_heading(
    'Account requests',
    'Review new registrations before granting access to the workspace.',
    '<a class="btn" href="index.php?page=users">Users & roles</a>',
);
$reviewStatus = ($_GET['status'] ?? '') === 'Rejected' ? 'Rejected' : 'Pending';
$pendingAccounts = all(
    'SELECT u.id,u.full_name,u.email,u.position,u.status,o.name office_name FROM users u JOIN offices o ON o.id=u.office_id WHERE u.status=? ORDER BY u.id',
    [$reviewStatus],
);
?>
<section class="panel">
    <form class="filter-bar" method="get">
        <input type="hidden" name="page" value="account-requests" /><label
            >Request status<select name="status">
                <option value="Pending" <?= $reviewStatus ===
                'Pending'
                    ? 'selected'
                    : '' ?>>Pending approval</option>
                <option value="Rejected" <?= $reviewStatus === 'Rejected'
                    ? 'selected'
                    : '' ?>>Rejected</option>
            </select></label
        ><button class="btn">View requests</button>
    </form>
    <div class="panel-heading">
        <div>
            <h2><?= e(
                $reviewStatus,
            ) ?> requests <span class="count-pill"><?= count(
                 $pendingAccounts,
             ) ?></span></h2>
            <p>
                Confirm each person’s identity and office. Only approval enables sign-in and adds
                the account to Users & roles.
            </p>
        </div>
    </div>
    <div class="form-body stack">
        <?php foreach ($pendingAccounts as $account): ?>
        <article class="registration-review">
            <h3><?= e($account['full_name']) ?></h3>
            <p><?= e(
                $account['email'],
            ) ?><br /><?= e($account['position']) ?> · <?= e($account['office_name']) ?></p>
            <?= badge(
                $account['status'],
            ) ?>
            <?php if (
                $reviewStatus === 'Pending'
            ): ?>
            <form action="actions.php" method="post" class="stack">
                <?= csrf() ?><input
                    type="hidden"
                    name="action"
                    value="review_registration"
                /><input type="hidden" name="id" value="<?= $account[
                    'id'
                ] ?>" /><input
                    type="hidden"
                    name="return_to"
                    value="index.php?page=account-requests"
                /><label
                    >Confirm your administrator password<input
                        type="password"
                        name="confirmation_password"
                        required
                        autocomplete="current-password"
                /></label>
                <div class="form-actions">
                    <button class="btn danger" name="decision" value="reject">Reject account</button
                    ><button class="btn primary" name="decision" value="approve">
                        Approve Requester account
                    </button>
                </div>
            </form>
            <?php else: ?>
            <p class="muted">This request was rejected. The account has no system access.</p>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
        <?php if (!$pendingAccounts): ?>
        <div class="empty-state">
            <h3>No <?= strtolower(
                e($reviewStatus),
            ) ?> account requests</h3>
            <p>Approved accounts are available in Users & roles.</p>
        </div>
        <?php endif; ?>
    </div>
</section>
