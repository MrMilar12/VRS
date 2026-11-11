<?php
$driverId = (int) ($user['driver_id'] ?? 0);
page_heading(
    'My trips',
    'See your assigned journeys and keep the travel team informed.',
);
if (!$driverId): ?>
<div class="alert error" role="alert">
    Your account is not linked to a driver personnel record. Contact your administrator to finish
    setting up your account.
</div>
<?php return;
endif;
$driver = one(
    'SELECT p.full_name,p.employee_number FROM personnel p WHERE p.driver_id=?',
    [$driverId],
);
$trips = driver_assigned_trips($driverId);
?>
<section class="driver-intro">
    <div>
        <span class="eyebrow">DRIVER WORKSPACE</span>
        <h2><?= e($driver['full_name'] ?? $user['full_name']) ?></h2>
        <p>Employee no. <?= e($driver['employee_number'] ?? '—') ?> · <?= count(
            $trips,
        ) ?> assigned trips</p>
    </div>
    <div class="driver-intro-icon"><?= icon('car', 30) ?></div>
</section>
<?php if (!$trips): ?>
<section class="panel empty-state driver-empty">
    <?= icon('calendar', 32) ?>
    <h3>No assigned trips yet</h3>
    <p>When a trip is assigned to you, it will appear here and you will receive a notification.</p>
</section>
<?php endif; ?>
<div class="driver-trip-list">
    <?php foreach ($trips as $trip):
        $progress = all(
            'SELECT update_type,remarks,created_at FROM driver_trip_reports WHERE requisition_id=? AND user_id=? ORDER BY created_at DESC,id DESC LIMIT 3',
            [$trip['id'], $user['id']],
        );
        $issues = all(
            'SELECT urgency,description,status,created_at FROM driver_vehicle_reports WHERE requisition_id=? AND user_id=? ORDER BY created_at DESC,id DESC LIMIT 3',
            [$trip['id'], $user['id']],
        );
        ?>
    <article class="panel driver-trip">
        <header class="driver-trip-heading">
            <div>
                <span class="driver-reference"><?= e($trip['reference']) ?></span>
                <h2><?= e($trip['destination']) ?></h2>
                <p><?= e($trip['model'] ?? 'Vehicle not assigned') ?> · <?= e(
                    $trip['plate'] ?? '—',
                ) ?></p>
            </div>
            <?= badge($trip['status']) ?>
        </header>
        <div class="driver-trip-meta">
            <div><span>DEPARTURE</span><strong><?= e(
                shortdate($trip['start_datetime'], 'M j, Y · g:i A'),
            ) ?></strong></div>
            <div><span>EXPECTED RETURN</span><strong><?= e(
                shortdate($trip['end_datetime'], 'M j, Y · g:i A'),
            ) ?></strong></div>
            <?php if ($trip['departure']): ?>
            <div><span>ACTUAL DEPARTURE</span><strong><?= e(
                shortdate($trip['departure'], 'M j, Y · g:i A'),
            ) ?></strong></div>
            <?php endif; ?>
        </div>
        <div class="driver-report-grid <?= in_array($trip['status'], ['Approved', 'Dispatched'], true) &&
        !empty($trip['vehicle_id'])
            ? ''
            : 'is-single' ?>">
            <?php if (in_array($trip['status'], ['Approved', 'Dispatched'], true)): ?>
            <form action="actions.php" method="post" class="driver-report-form">
                <?= csrf() ?><input type="hidden" name="action" value="driver_trip_report" /><input
                    type="hidden"
                    name="id"
                    value="<?= (int) $trip['id'] ?>"
                /><input type="hidden" name="return_to" value="index.php?page=driver" />
                <h3>Travel update</h3>
                <label>Update type<select name="update_type" required>
                    <option value="Departed">Departed</option>
                    <option value="Delayed">Delayed</option>
                    <option value="Arrived">Arrived</option>
                    <option value="Trip complete">Trip complete</option>
                    <option value="Other">Other</option>
                </select></label>
                <label>Details <span class="field-hint">Optional</span><textarea
                    name="remarks"
                    maxlength="1000"
                    rows="3"
                    placeholder="Share an ETA, stop, or other travel detail"
                ></textarea></label>
                <button class="btn primary" type="submit">Send travel update</button>
            </form>
            <?php endif; ?>
            <?php if (!empty($trip['vehicle_id'])): ?>
            <form action="actions.php" method="post" class="driver-report-form">
                <?= csrf() ?><input type="hidden" name="action" value="driver_vehicle_report" /><input
                    type="hidden"
                    name="id"
                    value="<?= (int) $trip['id'] ?>"
                /><input type="hidden" name="return_to" value="index.php?page=driver" />
                <h3>Vehicle issue</h3>
                <label>Urgency<select name="urgency" required>
                    <option value="Routine">Routine</option>
                    <option value="Urgent">Urgent</option>
                    <option value="Vehicle unsafe">Vehicle unsafe</option>
                </select></label>
                <label>Describe the issue<textarea
                    name="description"
                    maxlength="2000"
                    rows="3"
                    required
                    placeholder="Describe the issue and when it occurred"
                ></textarea></label>
                <button class="btn" type="submit">Report vehicle issue</button>
            </form>
            <?php endif; ?>
        </div>
        <?php if ($progress || $issues): ?>
        <details class="driver-history">
            <summary>Recent reports</summary>
            <?php foreach ($progress as $report): ?>
            <p><strong><?= e($report['update_type']) ?></strong> · <?= e(
                shortdate($report['created_at'], 'M j · g:i A'),
            ) ?><?= $report['remarks'] !== '' ? ' — ' . e($report['remarks']) : '' ?></p>
            <?php endforeach; ?>
            <?php foreach ($issues as $report): ?>
            <p><strong><?= e($report['urgency']) ?> vehicle issue</strong> · <?= e(
                shortdate($report['created_at'], 'M j · g:i A'),
            ) ?> — <?= e($report['description']) ?> (<?= e($report['status']) ?>)</p>
            <?php endforeach; ?>
        </details>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</div>
