<?php
require_once __DIR__ . '/dashboard-analytics.php';
$analyticsKind = in_array($_GET['analytics_kind'] ?? '', ['vehicle', 'personnel'], true)
    ? $_GET['analytics_kind']
    : 'all';
$analyticsPeriod = in_array($_GET['analytics_period'] ?? '', ['6', '12'], true)
    ? $_GET['analytics_period']
    : 'all';
$analytics = dashboard_analytics($rows, $personnelRows, $analyticsKind, $analyticsPeriod, $now);
$maxMonth = max(1, ...array_values(array_map(fn($v) => max($v), $analytics['months'])));
$vehicleShare = $analytics['total'] ? ($analytics['vehicle'] / $analytics['total']) * 100 : 0;
?>
<section class="overview-analytics" id="overview-analytics" aria-labelledby="analytics-heading">
    <div class="analytics-heading">
        <div>
            <span class="analytics-eyebrow">WORKSPACE INSIGHTS</span>
            <h2 id="analytics-heading">Your operations, at a glance</h2>
            <p>
                Requisitions you can access · grouped by creation date. Resource charts show current
                records.
            </p>
        </div>
        <form method="get" action="index.php#overview-analytics">
            <input type="hidden" name="page" value="dashboard" /><label
                class="sr-only"
                for="analytics-kind"
                >Requisition type</label
            ><select id="analytics-kind" name="analytics_kind">
                <?php foreach (
                    ['all' => 'All requisitions', 'vehicle' => 'Vehicle only', 'personnel' => 'Personnel only']
                    as $value => $label
                ): ?>
                <option value="<?= $value ?>" <?= $analyticsKind === $value
                    ? 'selected'
                    : '' ?>><?= $label ?></option>
                <?php endforeach; ?></select
            ><label class="sr-only" for="analytics-period">Analytics period</label
            ><select id="analytics-period" name="analytics_period">
                <?php foreach (
                    ['all' => 'All time', '6' => 'Last 6 months', '12' => 'Last 12 months']
                    as $value => $label
                ): ?>
                <option value="<?= $value ?>" <?= $analyticsPeriod === (string) $value
                    ? 'selected'
                    : '' ?>><?= $label ?></option>
                <?php endforeach; ?></select
            ><button class="btn primary">Apply</button>
        </form>
    </div>
    <div class="analytics-totals">
        <?php foreach (
            ['total' => 'Total requisitions', 'completed' => 'Completed', 'open' => 'Still open']
            as $key => $label
        ): ?>
        <div><span><?= $label ?></span><strong><?= number_format(
            $analytics[$key],
        ) ?></strong></div>
        <?php endforeach; ?>
        <div>
            <span>Completion rate</span><strong><?= $analytics[
                'total'
            ]
                ? round(($analytics['completed'] / $analytics['total']) * 100)
                : 0 ?><small>%</small></strong>
        </div>
    </div>
    <div class="analytics-grid">
        <article class="panel analytics-card analytics-trend">
            <div class="panel-heading">
                <div>
                    <h3>Requisition activity</h3>
                    <p>Requests created per month, including drafts</p>
                </div>
                <div class="analytics-legend">
                    <span><i></i>Vehicle</span><span><i class="personnel"></i>Personnel</span>
                </div>
            </div>
            <div
                class="analytics-chart-scroll"
                tabindex="0"
                aria-label="Monthly requisition chart, scroll for more months"
            >
                <div class="analytics-months" style="--months:<?= count(
                    $analytics['months'],
                ) ?>">
                    <?php foreach (
                        $analytics['months']
                        as $month => $counts
                    ): ?>
                    <div class="analytics-month">
                        <div class="analytics-columns">
                            <?php foreach (
                                $counts
                                as $type => $count
                            ): ?>
                            <div
                                class="analytics-column <?= $type ?>"
                                style="height:<?= ($count / $maxMonth) *
                                    150 ?>px"
                                title="<?= e(
                                    $month . ' · ' . ucfirst($type) . ': ' . $count,
                                ) ?>"
                            >
                                <span><?= $count ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <span><?= date(
                            'M',
                            strtotime($month . '-01'),
                        ) ?><small><?= substr(
                            $month,
                            0,
                            4,
                        ) ?></small></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <details class="analytics-data">
                <summary>View monthly data</summary>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Vehicle</th>
                                <th>Personnel</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (
                                $analytics['months']
                                as $month => $counts
                            ): ?>
                            <tr>
                                <th scope="row"><?= e($month) ?></th>
                                <td><?= $counts['vehicle'] ?></td>
                                <td><?= $counts[
                                    'personnel'
                                ] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        </article>
        <article class="panel analytics-card">
            <div class="panel-heading">
                <div>
                    <h3>Request mix</h3>
                    <p>Vehicle and personnel requisitions</p>
                </div>
            </div>
            <div class="analytics-mix">
                <div
                    class="analytics-ring"
                    style="--share:<?= $vehicleShare ?>%;<?= $analytics[
                        'total'
                    ]
                        ? ''
                        : 'background:var(--border)' ?>"
                    aria-hidden="true"
                >
                    <div><strong><?= number_format(
                        $analytics['total'],
                    ) ?></strong><span>requisitions</span></div>
                </div>
                <div class="analytics-mix-labels">
                    <span><i></i>Vehicle <strong><?= $analytics[
                        'vehicle'
                    ] ?></strong></span
                    ><span
                        ><i class="personnel"></i>Personnel <strong><?= $analytics[
                            'personnel'
                        ] ?></strong></span
                    >
                </div>
            </div>
        </article>
        <article class="panel analytics-card">
            <div class="panel-heading">
                <div>
                    <h3>Requisition status</h3>
                    <p>Current state of requests in this selection</p>
                </div>
            </div>
            <div class="analytics-bars"><?php analytics_bars(
                $analytics['statuses'],
            ); ?></div>
        </article>
        <article class="panel analytics-card">
            <div class="panel-heading">
                <div>
                    <h3>Requests by office</h3>
                    <p>Office totals within your access</p>
                </div>
            </div>
            <div class="analytics-bars"><?php analytics_bars(
                $analytics['offices'],
                'personnel',
            ); ?></div>
        </article>
        <?php
        if (manage()):

            $vehicleStates = [];
            foreach ($fleet as $v) {
                $state = in_array($v['id'], $activeIds)
                    ? 'In use'
                    : (in_array($v['id'], $blocked)
                        ? 'Under maintenance'
                        : (in_array($v['id'], $reservedIds)
                            ? 'Reserved'
                            : $v['status']));
                $vehicleStates[$state] = ($vehicleStates[$state] ?? 0) + 1;
            }
            $staffCounts = [];
            foreach (
                all('SELECT classification,status,COUNT(*) n FROM personnel GROUP BY classification,status')
                as $group
            ) {
                $staffCounts[$group['classification'] . ' · ' . $group['status']] = (int) $group['n'];
            }
            ?>
        <article class="panel analytics-card">
            <div class="panel-heading">
                <div>
                    <h3>Vehicle availability</h3>
                    <p>Current fleet · <?= count(
                        $fleet,
                    ) ?> vehicles</p>
                </div>
            </div>
            <div class="analytics-bars"><?php analytics_bars(
                 $vehicleStates,
             ); ?></div>
        </article>
        <article class="panel analytics-card">
            <div class="panel-heading">
                <div>
                    <h3>Personnel registry</h3>
                    <p>
                        Drivers and utility staff by registry status; availability also depends on
                        assignments
                    </p>
                </div>
            </div>
            <div class="analytics-bars"><?php analytics_bars(
                $staffCounts,
                'personnel',
            ); ?></div>
        </article>
        <?php
        endif;
        if (is_role('Administrator')):

            $accountStates = [];
            foreach (all('SELECT status,COUNT(*) n FROM users GROUP BY status') as $group) {
                $accountStates[$group['status']] = (int) $group['n'];
            }
            ?>
        <article class="panel analytics-card">
            <div class="panel-heading">
                <div>
                    <h3>Account access</h3>
                    <p>Current account statuses · administrators only</p>
                </div>
            </div>
            <div class="analytics-bars"><?php analytics_bars(
                $accountStates,
            ); ?></div>
        </article>
        <?php
        endif;
        ?>
    </div>
</section>
