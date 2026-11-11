<?php
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/database.php';
require __DIR__ . '/../includes/personnel.php';
require __DIR__ . '/../includes/driver.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/layout.php';
date_default_timezone_set('Asia/Manila');
$_SESSION = ['csrf' => 'driver-test-token'];
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys=ON');
initialize_database($pdo);
personnel_schema($pdo);
driver_schema($pdo);
function verify_driver(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    echo 'PASS: ' . $label . "\n";
}
$legacy = new PDO('sqlite::memory:');
$legacy->exec('CREATE TABLE users(id INTEGER PRIMARY KEY)');
$legacy->exec('CREATE TABLE requisitions(id INTEGER PRIMARY KEY)');
$legacy->exec('CREATE TABLE vehicles(id INTEGER PRIMARY KEY)');
driver_schema($legacy);
driver_schema($legacy);
$legacyColumns = $legacy->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
verify_driver(
    in_array('driver_id', array_column($legacyColumns, 'name'), true),
    'Driver schema upgrades existing databases idempotently',
);
run(
    "INSERT INTO drivers(full_name,employee_number,office_id,license_expiry,status) VALUES('Driver Test','DRV-TEST',4,'2030-01-01','Available')",
);
$driverId = (int) $pdo->lastInsertId();
personnel_schema($pdo);
$person = one('SELECT * FROM personnel WHERE driver_id=?', [$driverId]);
verify_driver(
    driver_registration_link('DRV-TEST', 'Driver Test', 4) === $driverId,
    'Driver registration resolves the matching personnel identity',
);
foreach (
    [
        ['DRV-TEST', 'Other Person', 4],
        ['DRV-TEST', 'Driver Test', 1],
        ['UNKNOWN', 'Driver Test', 4],
    ]
    as [$employeeNumber, $name, $officeId]
) {
    try {
        driver_registration_link($employeeNumber, $name, $officeId);
    } catch (RuntimeException) {
        continue;
    }
    throw new RuntimeException('FAIL: Invalid driver identity was accepted.');
}
verify_driver(true, 'Driver registration rejects mismatched identity details');
run(
    "INSERT INTO users(full_name,email,password_hash,role,office_id,position,status,driver_id) VALUES('Driver Test','driver-test@example.test','hash','Driver',4,'Driver','Active',?)",
    [$driverId],
);
$driverUserId = (int) $pdo->lastInsertId();
try {
    driver_registration_link('DRV-TEST', 'Driver Test', 4);
} catch (RuntimeException) {
    verify_driver(true, 'One driver record cannot be linked to duplicate accounts');
}
try {
    run(
        "INSERT INTO users(full_name,email,password_hash,role,office_id,position,status,driver_id) VALUES('Duplicate Driver','duplicate-driver@example.test','hash','Driver',4,'Driver','Pending',?)",
        [$driverId],
    );
    throw new RuntimeException('FAIL: Driver link uniqueness was not enforced by the database.');
} catch (PDOException) {
    verify_driver(true, 'Database constraint prevents concurrent duplicate driver links');
}
run(
    "INSERT INTO requisitions(reference,requester_id,office_id,vehicle_type,vehicle_id,driver_id,passengers,start_datetime,end_datetime,destination,purpose,status,created_at) VALUES('DRIVER-TEST',3,4,'Van',1,?,'Staff','2030-01-01 08:00:00','2030-01-01 12:00:00','Test destination','Test trip','Approved',?)",
    [$driverId, date('Y-m-d H:i:s')],
);
$tripId = (int) $pdo->lastInsertId();
$trips = driver_assigned_trips($driverId);
verify_driver(
    count($trips) === 1 && (int) $trips[0]['id'] === $tripId,
    'Driver trip list contains only trips assigned to that driver',
);
driver_notification($driverId, 'Trip assigned', $tripId);
verify_driver(
    (int) one('SELECT user_id FROM notifications WHERE requisition_id=?', [$tripId])['user_id'] ===
        $driverUserId,
    'Trip notifications are delivered to the linked active driver account',
);
$user = one('SELECT * FROM users WHERE id=1');
notify_fleet_managers('Driver report submitted', $tripId);
verify_driver(
    (int) one(
        'SELECT COUNT(*) n FROM notifications WHERE requisition_id=? AND user_id IN (1,4)',
        [$tripId],
    )['n'] === 2,
    'Driver reports can notify active fleet managers',
);
run(
    "INSERT INTO driver_trip_reports(requisition_id,user_id,update_type,remarks,created_at) VALUES(?,?,'Departed','On the way',?)",
    [$tripId, $driverUserId, date('Y-m-d H:i:s')],
);
run(
    "INSERT INTO driver_vehicle_reports(requisition_id,vehicle_id,user_id,urgency,description,created_at) VALUES(?,1,?,'Urgent','Flat tire',?)",
    [$tripId, $driverUserId, date('Y-m-d H:i:s')],
);
$issueId = (int) $pdo->lastInsertId();
verify_driver(
    count(all('SELECT id FROM driver_trip_reports WHERE user_id=?', [$driverUserId])) === 1 &&
        count(all('SELECT id FROM driver_vehicle_reports WHERE user_id=?', [$driverUserId])) === 1,
    'Trip progress and vehicle issue reports persist against their driver and assignment',
);
lock_transaction();
$lockedIssue = lock_record('driver_vehicle_reports', $issueId);
finish_transaction(true);
verify_driver(
    (int) ($lockedIssue['id'] ?? 0) === $issueId,
    'Fleet managers can lock an open driver report before resolving it',
);
$user = one('SELECT * FROM users WHERE id=?', [$driverUserId]);
ob_start();
require __DIR__ . '/../includes/driver-view.php';
$driverHtml = ob_get_clean();
verify_driver(
    str_contains($driverHtml, 'DRIVER-TEST') &&
        str_contains($driverHtml, 'driver_trip_report') &&
        str_contains($driverHtml, 'driver_vehicle_report'),
    'Driver workspace renders assigned trips and reporting forms',
);
$user = one('SELECT * FROM users WHERE id=1');
$page = 'maintenance';
ob_start();
require __DIR__ . '/../includes/other-pages.php';
$maintenanceHtml = ob_get_clean();
verify_driver(
    str_contains($maintenanceHtml, 'Driver vehicle reports') &&
        str_contains($maintenanceHtml, 'Flat tire') &&
        str_contains($maintenanceHtml, 'resolve_driver_vehicle_report'),
    'Maintenance page displays and can resolve driver vehicle reports',
);
echo "All driver checks passed.\n";
