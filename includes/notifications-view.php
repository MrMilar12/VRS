<?php
$notes = all('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC,id DESC', [
    $user['id'],
]);
$unreadCount = count(array_filter($notes, fn($note) => !$note['is_read']));
$onlyUnread = ($_GET['filter'] ?? '') === 'unread';
page_heading('Notifications', 'Your requests, account updates, and approvals in one place.');
?>
<section class="notification-center">
    <div class="notification-summary">
        <div class="notification-summary-icon"><?= icon(
            'bell',
            28,
        ) ?></div>
        <div class="notification-summary-copy">
            <span class="notification-eyebrow">YOUR INBOX</span>
            <h2><?= $unreadCount
                ? 'You have ' . $unreadCount . ' unread ' . ($unreadCount === 1 ? 'update' : 'updates')
                : 'You’re all caught up' ?></h2>
            <p><?= $unreadCount
                ? 'Stay up to date with the latest activity in your workspace.'
                : 'New updates will appear here when there’s activity.' ?></p>
        </div>
        <form action="actions.php" method="post">
            <?= csrf() ?><input type="hidden" name="action" value="read_notifications" /><button
                class="btn"
                <?= $unreadCount
                    ? ''
                    : 'disabled' ?>
            >
                <?= icon('check', 16) ?> Mark all as read
            </button>
        </form>
    </div>
    <nav class="notification-tabs" aria-label="Filter notifications">
        <a href="index.php?page=notifications" class="<?= $onlyUnread
            ? ''
            : 'selected' ?>" <?= $onlyUnread ? '' : 'aria-current="page"' ?>
            >All updates <span><?= count(
                $notes,
            ) ?></span></a
        ><a
            href="index.php?page=notifications&filter=unread"
            class="<?= $onlyUnread
                ? 'selected'
                : '' ?>"
            <?= $onlyUnread
                ? 'aria-current="page"'
                : '' ?>
            >Unread <span><?= $unreadCount ?></span></a
        >
    </nav>
    <div class="notification-feed">
        <?php
        $visible = $onlyUnread ? array_filter($notes, fn($note) => !$note['is_read']) : $notes;
        $lastGroup = '';
        foreach ($visible as $n):

            $day = date('Y-m-d', strtotime($n['created_at']));
            $group =
                $day === date('Y-m-d')
                    ? 'Today'
                    : ($day === date('Y-m-d', strtotime('-1 day'))
                        ? 'Yesterday'
                        : shortdate($n['created_at'], 'F j, Y'));
            $accountNotice = !$n['requisition_id'] && stripos($n['message'], 'account') !== false;
            $personnelNotice = !$n['requisition_id'] && stripos($n['message'], 'personnel') !== false;
            $category = $n['requisition_id']
                ? 'Vehicle requisition'
                : ($accountNotice
                    ? 'Account request'
                    : ($personnelNotice
                        ? 'Personnel requisition'
                        : 'Workspace update'));
            $target = $n['requisition_id']
                ? 'index.php?page=request&id=' . $n['requisition_id']
                : ($accountNotice && is_role('Administrator')
                    ? 'index.php?page=account-requests'
                    : ($personnelNotice
                        ? 'index.php?page=personnel-bookings'
                        : 'index.php?page=dashboard'));
            if ($group !== $lastGroup):
                $lastGroup = $group; ?>
        <h2 class="notification-date"><?= e($group) ?></h2>
        <?php
            endif;
            ?>
        <article class="notice-card <?= $n['is_read']
            ? ''
            : 'is-unread' ?>">
            <span class="notice-symbol" aria-hidden="true"><?= icon(
                $accountNotice
                    ? 'shield'
                    : ($personnelNotice
                        ? 'users'
                        : ($n['requisition_id']
                            ? 'car'
                            : 'bell')),
                21,
            ) ?></span>
            <div class="notice-content">
                <div class="notice-meta">
                    <span><?= e(
                        $category,
                    ) ?></span><?php if (
                        !$n['is_read']
                    ): ?><span class="notice-unread"
                        >Unread</span
                    ><?php endif; ?><time datetime="<?= e(
                        date('c', strtotime($n['created_at'])),
                    ) ?>"><?= shortdate($n['created_at'], 'g:i A') ?></time>
                </div>
                <a class="notice-message" href="<?= e(
                    secure_record_url($target),
                ) ?>"><?= e($n['message']) ?></a
                ><a class="notice-open" href="<?= e(
                    secure_record_url($target),
                ) ?>">View details <?= icon('chevron', 14) ?></a>
            </div>
            <div class="notice-actions"><?= delete_link(
                'notifications',
                $n,
            ) ?></div>
        </article>
        <?php
        endforeach;
        if (!$visible): ?>
        <div class="notification-empty">
            <span><?= icon(
                'check',
                32,
            ) ?></span>
            <h2><?= $onlyUnread
                ? 'No unread notifications'
                : 'Your inbox is clear' ?></h2>
            <p><?= $onlyUnread
                ? 'You’ve read all your updates.'
                : 'Updates about your requests and approvals will appear here.' ?></p>
            <?php if (
                $onlyUnread
            ): ?><a class="btn" href="index.php?page=notifications">View all updates</a
            ><?php endif; ?>
        </div>
        <?php endif;
        ?>
    </div>
</section>
