<?php
if ($page === 'maintenance') {
    $blocks = all(
        'SELECT b.*,v.model,v.plate FROM vehicle_blocks b JOIN vehicles v ON v.id=b.vehicle_id ORDER BY b.start_datetime DESC',
    );
    $parts = all(
        'SELECT p.*,v.model,v.plate FROM vehicle_parts p JOIN vehicles v ON v.id=p.vehicle_id ORDER BY p.procurement_date DESC,p.id DESC',
    );
    $vehicles = all('SELECT * FROM vehicles ORDER BY model,plate');
    ?>
<div class="maintenance-page">
    <header class="maintenance-overview">
        <div class="maintenance-overview-copy">
            <span class="maintenance-kicker">FLEET CARE</span>
            <h1>Maintenance</h1>
            <p>Keep service schedules and vehicle components organized in one place.</p>
        </div>
        <div class="maintenance-metrics">
            <div class="maintenance-metric">
                <span class="maintenance-metric-icon"><?= icon('car', 19) ?></span>
                <span><strong><?= count($vehicles) ?></strong><small>Vehicles</small></span>
            </div>
            <div class="maintenance-metric">
                <span class="maintenance-metric-icon"><?= icon('wrench', 19) ?></span>
                <span><strong><?= count($blocks) ?></strong><small>Service blocks</small></span>
            </div>
            <div class="maintenance-metric">
                <span class="maintenance-metric-icon"><?= icon('file', 19) ?></span>
                <span><strong><?= count($parts) ?></strong><small>Parts tracked</small></span>
            </div>
        </div>
        <div class="maintenance-tabs" role="tablist" aria-label="Maintenance sections" data-maintenance-tabs hidden>
            <button class="maintenance-tab is-active" id="maintenance-schedule-tab" type="button" role="tab" aria-selected="true" aria-controls="maintenance-schedule-panel" tabindex="0" data-maintenance-tab="schedule">
                <?= icon('calendar', 17) ?><span>Service schedule</span><span class="maintenance-tab-count"><?= count($blocks) ?></span>
            </button>
            <button class="maintenance-tab" id="maintenance-parts-tab" type="button" role="tab" aria-selected="false" aria-controls="maintenance-parts-panel" tabindex="-1" data-maintenance-tab="parts">
                <?= icon('file', 17) ?><span>Parts inventory</span><span class="maintenance-tab-count"><?= count($parts) ?></span>
            </button>
        </div>
    </header>

    <section class="maintenance-tabpanel" id="maintenance-schedule-panel" role="tabpanel" aria-labelledby="maintenance-schedule-tab" tabindex="0" data-maintenance-panel="schedule">
    <section class="maintenance-section" id="schedule">
        <div class="maintenance-section-heading">
            <div>
                <span class="maintenance-kicker">WORKSHOP</span>
                <h2>Service schedule</h2>
                <p>Coordinate planned downtime and review upcoming or past work.</p>
            </div>
        </div>
        <div class="maintenance-grid maintenance-schedule-grid">
            <section class="panel maintenance-panel">
                <div class="panel-heading maintenance-panel-heading">
                    <div>
                        <span class="maintenance-panel-icon"><?= icon('calendar', 18) ?></span>
                        <div><h2>Scheduled service</h2><p>Maintenance windows for your fleet</p></div>
                    </div>
                    <span class="maintenance-count"><?= count($blocks) ?></span>
                </div>
                <div class="table-scroll">
                    <table class="data-table maintenance-table">
                        <thead>
                            <tr><th>VEHICLE</th><th>SERVICE WINDOW</th><th>SERVICE / REASON</th><th></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocks as $block): ?>
                            <tr>
                                <td><strong><?= e($block['model']) ?></strong><small><?= e($block['plate']) ?></small></td>
                                <td><?= shortdate($block['start_datetime'], 'M j, Y') ?><small><?= shortdate($block['start_datetime'], 'g:i A') ?> – <?= shortdate($block['end_datetime'], 'M j, Y g:i A') ?></small></td>
                                <td><?= e($block['reason']) ?></td>
                                <td>
                                    <form action="actions.php" method="post" data-confirm="Remove this maintenance block?">
                                        <?= csrf() ?><input type="hidden" name="action" value="delete_block" />
                                        <input type="hidden" name="id" value="<?= $block['id'] ?>" />
                                        <button class="btn small danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$blocks): ?>
                            <tr><td class="maintenance-empty" colspan="4">
                                <span><?= icon('calendar', 22) ?></span>
                                <strong>No service scheduled</strong>
                                <small>Your maintenance blocks will appear here.</small>
                            </td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel maintenance-panel maintenance-form-panel">
                <div class="panel-heading maintenance-panel-heading">
                    <div>
                        <span class="maintenance-panel-icon"><?= icon('wrench', 18) ?></span>
                        <div><h2>Schedule maintenance</h2><p>Block a vehicle during service</p></div>
                    </div>
                </div>
                <form action="actions.php" method="post" class="form-body maintenance-form">
                    <?= csrf() ?><input type="hidden" name="action" value="maintenance" />
                    <input type="hidden" name="return_to" value="index.php?page=maintenance" />
                    <label class="maintenance-field-wide">Vehicle
                        <span class="maintenance-select-control">
                        <select name="vehicle_id" required <?= !$vehicles
                            ? 'aria-describedby="maintenance-schedule-vehicle-help"'
                            : '' ?>>
                            <option value="" selected disabled><?= $vehicles
                                ? 'Choose a vehicle'
                                : 'No vehicles available' ?></option>
                            <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= $vehicle['id'] ?>"><?= e($vehicle['model'] . ' · ' . $vehicle['plate']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </span>
                        <?php if (!$vehicles): ?>
                        <small class="maintenance-vehicle-help" id="maintenance-schedule-vehicle-help">
                            Add a vehicle before scheduling service. <a href="index.php?page=vehicles&add=1">Add vehicle</a>
                        </small>
                        <?php endif; ?>
                    </label>
                    <label>Starts<input type="datetime-local" name="start_datetime" required /></label>
                    <label>Ends<input type="datetime-local" name="end_datetime" required /></label>
                    <label class="maintenance-field-wide">Service / reason
                        <textarea name="reason" required rows="3" maxlength="1000" placeholder="Describe the planned service"></textarea>
                    </label>
                    <p class="maintenance-note"><?= icon('shield', 15) ?> Vehicles cannot be assigned during a scheduled service window.</p>
                    <button class="btn primary maintenance-submit" <?= !$vehicles
                        ? 'disabled'
                        : '' ?>><?= icon('calendar', 16) ?> Add service window</button>
                </form>
            </section>
        </div>
    </section>
    </section>

    <section class="maintenance-tabpanel" id="maintenance-parts-panel" role="tabpanel" aria-labelledby="maintenance-parts-tab" tabindex="0" data-maintenance-panel="parts">
    <section class="maintenance-section" id="parts">
        <div class="maintenance-section-heading maintenance-inventory-heading">
            <div>
                <span class="maintenance-kicker">REPLACEMENTS & EQUIPMENT</span>
                <h2>Parts inventory</h2>
                <p>Track what was procured, which vehicle it belongs to, and its expected lifespan.</p>
            </div>
        </div>
        <div class="maintenance-grid maintenance-inventory-grid">
            <section class="panel maintenance-panel maintenance-inventory-panel">
                <div class="panel-heading maintenance-panel-heading">
                    <div>
                        <span class="maintenance-panel-icon maintenance-parts-icon"><?= icon('file', 18) ?></span>
                        <div><h2>Installed & procured parts</h2><p>Vehicle components and service life</p></div>
                    </div>
                    <span class="maintenance-count"><?= count($parts) ?></span>
                </div>
                <div class="maintenance-parts-list">
                    <?php foreach ($parts as $part): ?>
                    <article class="maintenance-part-card">
                        <div class="maintenance-part-topline">
                            <span class="maintenance-part-symbol"><?= icon('settings', 19) ?></span>
                            <div class="maintenance-part-heading">
                                <h3><?= e($part['part_name']) ?></h3>
                                <span class="maintenance-brand"><?= e($part['brand']) ?></span>
                            </div>
                            <?php if (manage()): ?>
                            <form action="actions.php" method="post" data-confirm="Remove this part from the inventory?">
                                <?= csrf() ?><input type="hidden" name="action" value="delete_vehicle_part" />
                                <input type="hidden" name="id" value="<?= $part['id'] ?>" />
                                <button class="maintenance-remove-part" aria-label="Remove <?= e($part['part_name']) ?> from inventory" title="Remove part"><?= icon('plus', 15) ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <div class="maintenance-part-vehicle">
                            <?= icon('car', 14) ?>
                            <span><?= e($part['model']) ?></span>
                            <span class="maintenance-vehicle-plate"><?= e($part['plate']) ?></span>
                        </div>
                        <dl class="maintenance-part-details">
                            <div>
                                <dt>PROCURED</dt>
                                <dd><?= shortdate($part['procurement_date']) ?></dd>
                            </div>
                            <div>
                                <dt>EXPECTED LIFESPAN</dt>
                                <dd><span class="maintenance-lifespan"><?= e($part['lifespan']) ?></span></dd>
                            </div>
                        </dl>
                    </article>
                    <?php endforeach; ?>
                    <?php if (!$parts): ?>
                    <div class="maintenance-empty">
                        <span><?= icon('file', 22) ?></span>
                        <strong>Your parts inventory is ready</strong>
                        <small>Record a procured component to see its vehicle, brand, date, and expected lifespan here.</small>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php if (manage()): ?>
            <section class="panel maintenance-panel maintenance-form-panel maintenance-part-entry">
                <div class="panel-heading maintenance-panel-heading maintenance-part-entry-heading">
                    <div>
                        <span class="maintenance-panel-icon maintenance-parts-icon"><?= icon('plus', 18) ?></span>
                        <div><h2>Add a vehicle part</h2><p>Record a new procurement</p></div>
                    </div>
                </div>
                <form action="actions.php" method="post" class="form-body maintenance-form maintenance-part-form">
                    <?= csrf() ?><input type="hidden" name="action" value="add_vehicle_part" />
                    <input type="hidden" name="return_to" value="index.php?page=maintenance" />
                    <label class="maintenance-field-wide">Assign to vehicle
                        <span class="maintenance-select-control">
                        <select name="vehicle_id" required <?= !$vehicles
                            ? 'aria-describedby="maintenance-parts-vehicle-help"'
                            : '' ?>>
                            <option value="" selected disabled><?= $vehicles
                                ? 'Choose a vehicle'
                                : 'No vehicles available' ?></option>
                            <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?= $vehicle['id'] ?>"><?= e($vehicle['model'] . ' · ' . $vehicle['plate']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </span>
                        <?php if (!$vehicles): ?>
                        <small class="maintenance-vehicle-help" id="maintenance-parts-vehicle-help">
                            Add a vehicle before recording parts. <a href="index.php?page=vehicles&add=1">Add vehicle</a>
                        </small>
                        <?php endif; ?>
                    </label>
                    <label class="maintenance-field-wide">Part name
                        <input name="part_name" maxlength="120" placeholder="e.g. All-terrain tires" required />
                    </label>
                    <label class="maintenance-field-wide">Brand
                        <input name="brand" maxlength="120" placeholder="e.g. Michelin" required />
                    </label>
                    <label class="maintenance-field-wide">Procurement date
                        <input type="date" name="procurement_date" required />
                    </label>
                    <label class="maintenance-field-wide">Expected lifespan
                        <input name="lifespan" maxlength="120" placeholder="e.g. 3 years or 60,000 km" required />
                    </label>
                    <div class="maintenance-entry-note">
                        <span><?= icon('shield', 15) ?></span>
                        <p>Keep part details consistent so replacements are easy to track.</p>
                    </div>
                    <button class="btn primary maintenance-submit" <?= !$vehicles
                        ? 'disabled'
                        : '' ?>><?= icon('plus', 16) ?> Save part to inventory</button>
                </form>
            </section>
            <?php endif; ?>
        </div>
    </section>
</div>
<?php
} elseif ($page === 'settings') {
    page_heading(
        'Settings',
        'Set the rules and details for your workspace.',
    ); ?>
<section class="panel form-panel">
    <div class="panel-heading"><h2>Organization & scheduling</h2></div>
    <form action="actions.php" method="post" class="form-body stack">
        <?= csrf() ?><input type="hidden" name="action" value="settings" /><input
            type="hidden"
            name="return_to"
            value="index.php?page=settings"
        /><?php foreach (
            [
                'organization' => 'Organization name',
                'supervisor_signatory' => 'Supervisor signature label',
                'admin_signatory' => 'Administrative signature label',
                'turnaround_minutes' => 'Turnaround between trips (minutes)',
            ]
            as $k => $label
        ): ?><label
            ><?= $label ?><input
                name="<?= $k ?>"
                value="<?= e(setting($k)) ?>"
                type="<?= $k ===
                'turnaround_minutes'
                    ? 'number'
                    : 'text' ?>"
                <?= $k === 'turnaround_minutes'
                    ? 'min="0" max="1440"'
                    : 'maxlength="255"' ?>
                required /></label
        ><?php endforeach; ?><button class="btn primary">Save settings</button>
    </form>
</section>
<?php
} elseif ($page === 'notifications') {
    require __DIR__ . '/notifications-view.php';
} elseif ($page === 'audit') {

    page_heading('Audit trail', 'A traceable record of important workspace activity.');
    $query = trim((string) ($_GET['q'] ?? ''));
    $logs = all(
        'SELECT a.*,u.full_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 500',
    );
    if ($query) {
        $logs = array_filter(
            $logs,
            fn($l) => stripos(
                $l['action'] . ' ' . $l['details'] . ' ' . $l['full_name'],
                $query,
            ) !== false,
        );
    }
    ?>
<section class="panel">
    <form class="filter-bar">
        <input type="hidden" name="page" value="audit" />
        <div class="search-field">
            <?= icon(
                'search',
                18,
            ) ?><input
                name="q"
                value="<?= e(
                    $query,
                ) ?>"
                placeholder="Search recent activity"
                aria-label="Search audit trail"
            />
        </div>
        <button class="btn">Search</button>
    </form>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>DATE & TIME</th>
                    <th>USER</th>
                    <th>ACTION</th>
                    <th>DETAILS</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (
                    $logs
                    as $l
                ): ?>
                <tr>
                    <td><?= e($l['created_at']) ?></td>
                    <td><?= e(
                        $l['full_name'] ?? 'System',
                    ) ?></td>
                    <td><strong><?= e($l['action']) ?></strong></td>
                    <td><?= e(
                        $l['details'],
                    ) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">Showing up to 500 most recent actions</div>
</section>
<?php
} elseif ($page === 'reports') {

    require __DIR__ . '/report-data.php';
    page_heading(
        'Reports & insights',
        'Turn your fleet activity into a clearer picture.',
        '<a class="btn" href="export.php?' .
            e(http_build_query(['from' => $from, 'to' => $to, 'office' => $office])) .
            '">' .
            icon('chart', 17) .
            ' Export CSV</a><button class="btn" onclick="window.print()">' .
            icon('print', 17) .
            ' Print report</button>',
    );
    ?>
<section class="panel">
    <form class="filter-bar" method="get">
        <input type="hidden" name="page" value="reports" /><label
            >From<input type="date" name="from" value="<?= e(
                $from,
            ) ?>" required /></label
        ><label>To<input type="date" name="to" value="<?= e(
            $to,
        ) ?>" required /></label
        ><label
            >Office<select name="office">
                <option value="">All offices</option>
                <?php foreach (
                    all('SELECT * FROM offices')
                    as $o
                ): ?>
                <option value="<?= $o['id'] ?>" <?= $office == $o['id'] ? 'selected' : '' ?>><?= e(
                    $o['name'],
                ) ?></option>
                <?php endforeach; ?>
            </select></label
        ><button class="btn primary">Apply filters</button>
    </form>
</section>
<section class="stat-grid report-stats">
    <?php foreach (
        [
            ['Total requisitions', count($reportRows)],
            [
                'Completed trips',
                count(array_filter($reportRows, fn($r) => $r['status'] === 'Completed')),
            ],
            [
                'Distance travelled',
                number_format(array_sum(array_column($reportRows, 'distance'))) . ' km',
            ],
            [
                'Fuel requested',
                number_format(array_sum(array_column($reportRows, 'fuel_quantity')), 1) . ' L',
            ],
        ]
        as [$label, $value]
    ): ?>
    <div class="stat-card">
        <span><?= $label ?></span><strong class="stat-number"><?= $value ?></strong
        ><small>Selected reporting period</small>
    </div>
    <?php endforeach; ?>
</section>
<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-heading">
            <div>
                <h2>Trips by office</h2>
                <p>Requisitions in the selected period</p>
            </div>
        </div>
        <div class="report-bars">
            <?php
            $groups = [];
            foreach ($reportRows as $r) {
                $groups[$r['office_name']] = ($groups[$r['office_name']] ?? 0) + 1;
            }
            arsort($groups);
            foreach ($groups as $label => $count): ?>
            <div>
                <span><?= e(
                    $label,
                ) ?><b><?= $count ?></b></span>
                <div class="bar-track"><i style="width:<?= count($reportRows)
                    ? ($count / count($reportRows)) * 100
                    : 0 ?>%"></i></div>
            </div>
            <?php endforeach;
            if (!$groups): ?>
            <p class="muted">No trips in this period.</p>
            <?php endif;
            ?>
        </div>
    </section>
    <section class="panel">
        <div class="panel-heading"><h2>Trip outcomes</h2></div>
        <div class="fleet-legend report-legend">
            <?php foreach (
                ['Approved', 'Dispatched', 'Returned', 'Completed', 'Cancelled', 'Rejected']
                as $status
            ): ?>
            <div><?= badge($status) ?><strong><?= count(
                array_filter($reportRows, fn($r) => $r['status'] === $status),
            ) ?></strong></div>
            <?php endforeach; ?>
            <div><span>Late returns / overdue</span><strong><?= count(
                array_filter($reportRows, fn($r) => $r['late']),
            ) ?></strong></div>
        </div>
    </section>
</div>
<section class="panel">
    <div class="panel-heading">
        <div>
            <h2>Vehicle utilization</h2>
            <p>
                Approved, dispatched, returned and completed trips; scheduled hours clipped to the
                reporting period
            </p>
        </div>
    </div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>VEHICLE</th>
                    <th>TRIPS</th>
                    <th>SCHEDULED HOURS</th>
                    <th>DISTANCE</th>
                    <th>FUEL REQUESTED</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (
                    all('SELECT * FROM vehicles')
                    as $v
                ):

                    $vr = array_filter(
                        $reportRows,
                        fn($r) => $r['vehicle_id'] == $v['id'] &&
                            in_array($r['status'], ['Approved', 'Dispatched', 'Returned', 'Completed']),
                    );
                    $hours = 0;
                    foreach ($vr as $r) {
                        $hours +=
                            max(
                                0,
                                min(strtotime($r['end_datetime']), strtotime($to . ' +1 day')) -
                                    max(strtotime($r['start_datetime']), strtotime($from)),
                            ) / 3600;
                    }
                    ?>
                <tr>
                    <td><strong><?= e($v['model']) ?></strong><small><?= e(
                        $v['plate'],
                    ) ?></small></td>
                    <td><?= count($vr) ?></td>
                    <td><?= number_format(
                        $hours,
                        1,
                    ) ?></td>
                    <td><?= number_format(
                        array_sum(array_column($vr, 'distance')),
                    ) ?> km</td>
                    <td><?= number_format(
                         array_sum(array_column($vr, 'fuel_quantity')),
                         1,
                     ) ?> L</td>
                </tr>
                <?php
                endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel">
    <div class="panel-heading"><h2>Trip, driver & mileage records</h2></div>
    <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>REFERENCE / REQUESTER</th>
                    <th>DRIVER</th>
                    <th>DESTINATION</th>
                    <th>DISTANCE</th>
                    <th>RETURN</th>
                    <th>STATUS</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($reportRows as $r): ?>
                <tr>
                    <td>
                        <a class="table-title" href="<?= e(
                            secure_record_url('index.php?page=request&id=' . $r['id']),
                        ) ?>"><?= e($r['reference']) ?></a
                        ><small><?= e($r['requester_name']) ?></small>
                    </td>
                    <td><?= e(
                        $r['driver_name'] ?? 'Unassigned',
                    ) ?></td>
                    <td><?= e($r['destination']) ?></td>
                    <td><?= number_format(
                        $r['distance'],
                    ) ?> km</td>
                    <td><?= $r['late']
                         ? badge('Overdue')
                         : e($r['return_time'] ?? '—') ?></td>
                    <td><?= badge($r['status']) ?></td>
                </tr>
                <?php endforeach;
                if (!$reportRows): ?>
                <tr>
                    <td colspan="6">No trips match this reporting period.</td>
                </tr>
                <?php endif;
                ?>
            </tbody>
        </table>
    </div>
</section>
<?php
} else {
    page_heading(
        'Page unavailable',
        $page === 'forbidden'
            ? 'Your role does not have access to this page.'
            : 'The page you requested could not be found.',
        '<a class="btn primary" href="index.php">Go to overview</a>',
    );
}
