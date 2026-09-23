<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!$user) {
    redirect('../login.php');
}
try {
    $r = personnel_booking(record_link_id($_GET['id'] ?? '', 'personnel-print:id'));
    if (!in_array($r['status'], ['Approved', 'In Progress', 'Completed'])) {
        throw new RuntimeException('Only approved personnel requisitions can be printed.');
    }
    $approval = one(
        "SELECT h.*,u.full_name FROM personnel_booking_history h JOIN users u ON u.id=h.user_id WHERE h.booking_id=? AND h.decision='Approved' ORDER BY h.id DESC",
        [$r['id']],
    );
} catch (RuntimeException $e) {
    http_response_code(403);
    exit(e($e->getMessage()));
}
$staff = $r['assigned_personnel'];
if (!$staff && !empty($r['personnel_name'])) {
    $staff = [
        [
            'full_name' => $r['personnel_name'],
            'position' => $r['personnel_position'],
            'classification' => '',
            'employee_number' => '',
        ],
    ];
}
?><!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?= e($r['reference']) ?> · Personnel requisition</title>
        <link rel="stylesheet" href="../assets/css/personnel-slip.css?v=<?= filemtime(
            __DIR__ . '/../assets/css/personnel-slip.css',
        ) ?>" />
        <script src="../assets/js/vendor/qrcodegen.js" defer></script>
        <script src="../assets/js/slip-qr.js?v=<?= filemtime(
            __DIR__ . '/../assets/js/slip-qr.js',
        ) ?>" defer></script>
    </head>
    <body>
        <nav class="print-toolbar" aria-label="Slip actions">
            <a href="<?= e(
                secure_record_url('../index.php?page=personnel-request&id=' . $r['id']),
            ) ?>">← Back to requisition</a
            ><button type="button" data-print-slip disabled>Preparing QR code…</button
            ><span data-qr-status role="status"></span
            ><noscript>Enable JavaScript to generate the QR code before printing.</noscript>
        </nav>
        <main class="personnel-slip">
            <header class="slip-header">
                <div>
                    <p class="organization-name"><?= e(
                        setting('organization'),
                    ) ?></p>
                    <h1>PERSONNEL REQUISITION</h1>
                    <p class="document-reference">
                        Reference: <strong><?= e(
                            $r['reference'],
                        ) ?></strong> <span>·</span> <?= e(
                            $r['status'],
                        ) ?>
                    </p>
                </div>
                <figure class="personnel-qr">
                    <div data-slip-qr="<?= e(
                        $r['reference'],
                    ) ?>"></div>
                    <figcaption><?= e($r['reference']) ?></figcaption>
                </figure>
            </header>
            <section class="form-section">
                <h2>Requesting office</h2>
                <table class="details-table">
                    <tbody>
                        <?php foreach (
                            [
                                'Requested by' => $r['requester_name'],
                                'Office / division' => $r['office_name'],
                                'Date requested' => shortdate($r['created_at'], 'M j, Y'),
                            ]
                            as $label => $value
                        ): ?>
                        <tr>
                            <th scope="row"><?= e($label) ?></th>
                            <td><?= e($value) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <section class="form-section">
                <h2>Assignment details</h2>
                <table class="details-table">
                    <tbody>
                        <?php foreach (
                            [
                                'Start date & time' => shortdate($r['start_datetime'], 'M j, Y · g:i A'),
                                'End date & time' => shortdate($r['end_datetime'], 'M j, Y · g:i A'),
                                'Location / destination' => $r['destination'],
                                'Required position / specialty' => $r['requested_role'],
                                'Purpose / duties' => $r['purpose'],
                            ]
                            as $label => $value
                        ): ?>
                        <tr class="<?= $label === 'Purpose / duties' ? 'long-text' : '' ?>">
                            <th scope="row"><?= e(
                                $label,
                            ) ?></th>
                            <td><?= nl2br(e($value)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <section class="form-section">
                <h2>Assigned personnel <small><?= count(
                    $staff,
                ) ?> <?= count($staff) === 1
                    ? 'person'
                    : 'people' ?></small></h2>
                <table class="details-table preference-table">
                    <tbody>
                        <tr>
                            <th scope="row">Preferred personnel</th>
                            <td><?= e(
                                $r['preferred_personnel'] ?: 'No preference indicated',
                            ) ?></td>
                        </tr>
                    </tbody>
                </table>
                <table class="personnel-table">
                    <caption class="sr-only">
                        Personnel authorized for this assignment
                    </caption>
                    <colgroup>
                        <col class="name-column" />
                        <col class="employee-column" />
                        <col class="classification-column" />
                        <col />
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Employee no.</th>
                            <th scope="col">Classification</th>
                            <th scope="col">Position / specialty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staff as $person): ?>
                        <tr>
                            <td><strong><?= e(
                                $person['full_name'],
                            ) ?></strong></td>
                            <td><?= e($person['employee_number'] ?: '—') ?></td>
                            <td><?= e(
                                $person['classification'] ?: '—',
                            ) ?></td>
                            <td><?= e($person['position'] ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <section class="form-section">
                <h2>Approval</h2>
                <table class="details-table">
                    <tbody>
                        <?php foreach (
                            [
                                'Approved by' => $approval['full_name'] ?? '—',
                                'Approval date' => !empty($approval['created_at'])
                                    ? shortdate($approval['created_at'], 'M j, Y · g:i A')
                                    : '—',
                                'Approval remarks' => !empty($approval['remarks']) ? $approval['remarks'] : 'No remarks',
                            ]
                            as $label => $value
                        ): ?>
                        <tr class="<?= $label === 'Approval remarks' ? 'long-text' : '' ?>">
                            <th scope="row"><?= e(
                                $label,
                            ) ?></th>
                            <td><?= nl2br(e($value)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="signatures">
                    <div>
                        <p>Requested by</p>
                        <div class="signature-line"></div>
                        <strong><?= e(
                            $r['requester_name'],
                        ) ?></strong><small>Signature over printed name</small>
                    </div>
                    <div>
                        <p>Approved by</p>
                        <div class="signature-line"></div>
                        <strong><?= e(
                            $approval['full_name'] ?? '',
                        ) ?></strong
                        ><small>Administrator · Signature over printed name</small>
                    </div>
                </div>
            </section>
            <?php if (
                !empty($r['actual_start']) ||
                !empty($r['actual_end'])
            ): ?>
            <section class="form-section">
                <h2>Actual assignment record</h2>
                <table class="details-table">
                    <tbody>
                        <tr>
                            <th scope="row">Actual start</th>
                            <td><?= !empty(
                                $r['actual_start']
                            )
                                ? shortdate($r['actual_start'], 'M j, Y · g:i A')
                                : 'Not recorded' ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Actual completion</th>
                            <td><?= !empty(
                                $r['actual_end']
                            )
                                ? shortdate($r['actual_end'], 'M j, Y · g:i A')
                                : 'Not recorded' ?></td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <?php endif; ?>
            <footer class="slip-footer">Printed by <?= e($user['full_name']) ?> · <?= date(
                 'M j, Y · g:i A',
             ) ?></footer>
        </main>
    </body>
</html>
