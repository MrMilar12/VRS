<?php
// Uses the already authorized and filtered request list; no separate tracking lookup.
$vehicleStages = ['Submitted', 'Approved', 'Dispatched', 'Returned', 'Completed'];
$vehiclePositions = [
    'Draft' => 0,
    'Returned for Correction' => 0,
    'Pending Supervisor' => 1,
    'Pending Administrative Approval' => 1,
    'Approved' => 2,
    'Dispatched' => 3,
    'Returned' => 4,
    'Completed' => 5,
];
?><link rel="stylesheet" href="assets/css/tracking.css?v=<?= filemtime(
    __DIR__ . '/../assets/css/tracking.css',
) ?>" />
<div class="tracking-workspace">
    <section class="tracking-hero">
        <div>
            <span class="tracking-eyebrow">REQUISITION TRACKING</span>
            <h1>Your request, at a glance.</h1>
            <p>Find a slip, check its progress, and see assigned personnel or vehicle details.</p>
        </div>
        <span class="tracking-hero-mark" aria-hidden="true"><?= icon(
             'search',
             32,
         ) ?></span>
    </section>
    <form
        class="tracking-search"
        action="index.php"
        method="get"
        role="search"
        aria-label="Track requisitions"
    >
        <input type="hidden" name="page" value="requisitions" />
        <label
            >Slip reference or personnel name<input
                type="search"
                name="q"
                value="<?= e(
                      $q,
                  ) ?>"
                placeholder="Reference, personnel, or destination"
                aria-label="Search reference, personnel, destination, or requester"
        /></label>
        <label
            >Request status<select name="status">
                <option value="">All statuses</option>
                <?php foreach (
                      $statuses
                      as $s
                  ): ?>
                <option <?= $s === $status ? 'selected' : '' ?>><?= e(
                    $s,
                ) ?></option>
                <?php endforeach; ?>
            </select></label
        >
        <label
            >Requesting office<select name="office">
                <option value="">All offices</option>
                <?php foreach (
                      $offices
                      as $id => $o
                  ): ?>
                <option value="<?= $id ?>" <?= $office == $id ? 'selected' : '' ?>><?= e(
                    $o,
                ) ?></option>
                <?php endforeach; ?>
            </select></label
        >
        <div class="tracking-search-actions">
            <button type="submit" class="btn primary"><?= icon(
                  'search',
                  16,
              ) ?> Search</button
            ><a class="text-link" href="index.php?page=requisitions">Clear</a>
        </div>
    </form>
    <div class="tracking-toolbar">
        <div>
            <h2><?= count($filtered) ?> matching request<?= count(
                 $filtered,
             ) === 1
                 ? ''
                 : 's' ?></h2>
            <p>Results for “<?= e(
                $q,
            ) ?>”</p>
        </div>
        <a class="btn" href="index.php?page=requisitions">All requisitions <?= icon(
            'arrow',
            15,
        ) ?></a>
    </div>
    <?php if (!$filtered): ?>
    <section class="tracking-empty">
        <?= icon(
            'file',
            32,
        ) ?>
        <h2>No matching slips</h2>
        <p>
            Check the reference from your QR code, try a personnel name or destination, or clear the
            filters.
        </p>
        <a class="btn" href="index.php?page=requisitions">View requisitions</a>
    </section>
    <?php else: ?>
    <div class="tracking-results">
        <?php foreach ($filtered as $r):

             $personnel = ($r['request_kind'] ?? '') === 'Personnel';
             $stages = $personnel ? ['Submitted', 'Approved', 'In Progress', 'Completed'] : $vehicleStages;
             $positions = $personnel
                 ? [
                     'Draft' => 0,
                     'Returned for Correction' => 0,
                     'Pending Administrative Approval' => 1,
                     'Approved' => 2,
                     'In Progress' => 3,
                     'Completed' => 4,
                 ]
                 : $vehiclePositions;
             $position = $positions[$r['status']] ?? 0;
             $url = secure_record_url(
                 'index.php?page=' . ($personnel ? 'personnel-request' : 'request') . '&id=' . $r['id'],
             );
             $note = match ($r['status']) {
                 'Draft' => 'Not yet submitted.',
                 'Returned for Correction' => 'Update the details and resubmit.',
                 'Pending Supervisor',
                 'Pending Administrative Approval'
                     => 'Awaiting administrator review.',
                 'Approved' => $personnel
                     ? 'Approved and awaiting the start of the assignment.'
                     : 'Approved and awaiting vehicle release.',
                 'In Progress' => 'Personnel assignment is in progress.',
                 'Dispatched' => 'Vehicle released; awaiting its return.',
                 'Returned' => 'Return recorded; awaiting completion.',
                 'Completed' => $personnel
                     ? 'This personnel assignment is complete.'
                     : 'This trip is complete.',
                 'Cancelled' => 'This request has been cancelled.',
                 'Rejected' => 'This request was rejected.',
                 default => 'Open the request for more details.',
             };
             ?>
        <article class="tracking-card">
            <div class="tracking-card-top">
                <a class="tracking-reference" href="<?= e($url) ?>"><?= e(
                    $r['reference'],
                ) ?></a
                ><?= badge($r['status']) ?>
            </div>
            <p class="tracking-owner tracking-kind"><?= $personnel
                  ? 'Personnel requisition'
                  : 'Vehicle requisition' ?></p>
            <h2><?= nl2br(
                e($r['destination']),
            ) ?></h2>
            <p class="tracking-owner"><?= e($r['requester_name']) ?> · <?= e($r['office_name']) ?></p>
            <dl class="tracking-details">
                <div>
                    <dt><?= $personnel
                          ? 'Start'
                          : 'Departure' ?></dt>
                    <dd><?= shortdate($r['start_datetime'], 'M j, Y') ?><small><?= shortdate(
                        $r['start_datetime'],
                        'g:i A',
                    ) ?></small></dd>
                </div>
                <div>
                    <dt><?= $personnel
                        ? 'End'
                        : 'Estimated return' ?></dt>
                    <dd><?= shortdate(
                        $r['end_datetime'],
                        'M j, Y',
                    ) ?><small><?= shortdate($r['end_datetime'], 'g:i A') ?></small></dd>
                </div>
                <?php if (
                    $personnel
                ): ?>
                <div>
                    <dt>Assigned personnel</dt>
                    <dd><?= e(
                        $r['personnel_name'] ?? 'Awaiting assignment',
                    ) ?></dd>
                </div>
                <div>
                    <dt>Required position / specialty</dt>
                    <dd><?= e(
                        $r['requested_role'],
                    ) ?></dd>
                </div>
                <?php if ($r['actual_start']): ?>
                <div>
                    <dt>Actual start</dt>
                    <dd><?= shortdate(
                        $r['actual_start'],
                        'M j, Y · g:i A',
                    ) ?></dd>
                </div>
                <?php endif; ?><?php if (
                    $r['actual_end']
                ): ?>
                <div>
                    <dt>Actual completion</dt>
                    <dd><?= shortdate(
                        $r['actual_end'],
                        'M j, Y · g:i A',
                    ) ?></dd>
                </div>
                <?php endif; ?><?php else: ?>
                <div>
                    <dt>Vehicle</dt>
                    <dd><?= e(
                        $r['model'] ?? 'Awaiting assignment',
                    ) ?><small><?= e(
                        $r['plate'] ?? $r['vehicle_type'],
                    ) ?></small></dd>
                </div>
                <div>
                    <dt>Driver</dt>
                    <dd><?= e(
                        $r['driver_name'] ?? 'Awaiting assignment',
                    ) ?></dd>
                </div>
                <?php endif; ?>
            </dl>
            <?php if (
                  !in_array($r['status'], ['Cancelled', 'Rejected'])
              ): ?>
            <ol
                class="tracking-progress <?= $personnel
                    ? 'personnel-progress'
                    : '' ?>"
                aria-label="Request progress: <?= e($r['status']) ?>"
            >
                <?php foreach (
                    $stages
                    as $index => $label
                ):
                    $step = $index + 1; ?>
                <li class="<?= $step <= $position
                    ? 'is-reached'
                    : '' ?> <?= $step === $position ? 'is-current' : '' ?>" <?= $step === $position
                        ? 'aria-current="step"'
                        : '' ?>>
                    <span aria-hidden="true"><?= $step < $position ? '✓' : $step ?></span><span><?= e(
                        $label,
                    ) ?></span>
                </li>
                <?php
                endforeach; ?>
            </ol>
            <?php endif; ?>
            <div class="tracking-card-footer">
                <p><?= e($note) ?></p>
                <a
                    class="btn"
                    href="<?= e(
                        $url,
                    ) ?>"
                    aria-label="View details for <?= e($r['reference']) ?>"
                    >View details <?= icon(
                        'chevron',
                        15,
                    ) ?></a
                >
            </div>
        </article>
        <?php
         endforeach; ?>
    </div>
    <?php endif; ?>
</div>
