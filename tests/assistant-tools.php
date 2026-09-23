<?php
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/database.php';
require __DIR__ . '/../includes/booking-assistant.php';
require __DIR__ . '/../includes/assistant-tools.php';
date_default_timezone_set('Asia/Manila');
$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
initialize_database($pdo);
require __DIR__ . '/../includes/personnel.php';
personnel_schema($pdo);
function check_tool(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo 'PASS: ' . $label . "\n";
}
run('DELETE FROM requisitions');
run('DELETE FROM vehicle_blocks');
$lookup = [
    'search' => 'SAB 1234',
    'status' => 'all',
    'start_datetime' => date('Y-m-d', strtotime('+2 days')) . 'T08:00',
    'end_datetime' => date('Y-m-d', strtotime('+2 days')) . 'T17:00',
];
$r = assistant_availability('vehicles', $lookup);
check_tool(
    count($r['items']) === 1 && $r['items'][0]['available'],
    'Plate search returns an available vehicle',
);
run(
    "INSERT INTO requisitions(reference,requester_id,office_id,vehicle_type,vehicle_id,driver_id,passengers,start_datetime,end_datetime,destination,purpose,status,created_at) VALUES('SECRET-TRIP',1,1,'Van',1,1,'PRIVATE PASSENGER',?,?,'PRIVATE DESTINATION','PRIVATE PURPOSE','Approved',?)",
    [
        str_replace('T', ' ', $lookup['start_datetime']) . ':00',
        str_replace('T', ' ', $lookup['end_datetime']) . ':00',
        date('Y-m-d H:i:s'),
    ],
);
$r = assistant_availability('vehicles', $lookup);
check_tool(!$r['items'][0]['available'], 'Approved trips block availability');
$encoded = json_encode($r);
check_tool(
    !str_contains($encoded, 'PRIVATE') && !str_contains($encoded, 'SECRET-TRIP'),
    'Availability never reveals private trip details or references',
);
check_tool(
    !assistant_availability('drivers', array_replace($lookup, ['search' => 'Juan']))['items'][0][
        'available'
    ],
    'Driver booking conflicts are checked',
);
check_tool(
    !assistant_availability('vehicles', array_replace($lookup, ['status' => 'available']))['items'],
    'Available filter excludes occupied vehicles',
);
run("UPDATE requisitions SET status='Completed'");
check_tool(
    assistant_availability('vehicles', $lookup)['items'][0]['available'],
    'Completed trips release availability',
);
run("UPDATE vehicles SET registration_expiry='2000-01-01' WHERE id=1");
check_tool(
    !assistant_availability('vehicles', $lookup)['items'][0]['available'],
    'Expired registrations block vehicles',
);
run("UPDATE drivers SET status='On Leave' WHERE id=1");
check_tool(
    !assistant_availability('drivers', array_replace($lookup, ['search' => 'Juan']))['items'][0][
        'available'
    ],
    'Drivers on leave are unavailable',
);
run("UPDATE drivers SET status='Available',license_expiry='2000-01-01' WHERE id=1");
check_tool(
    !assistant_availability('drivers', array_replace($lookup, ['search' => 'Juan']))['items'][0][
        'available'
    ],
    'Expired licenses block drivers',
);
run('INSERT INTO vehicle_blocks(vehicle_id,start_datetime,end_datetime,reason) VALUES(2,?,?,?)', [
    str_replace('T', ' ', $lookup['start_datetime']) . ':00',
    str_replace('T', ' ', $lookup['end_datetime']) . ':00',
    'PRIVATE MAINTENANCE NOTE',
]);
check_tool(
    !assistant_availability('vehicles', array_replace($lookup, ['search' => 'SAC 5678']))[
        'items'
    ][0]['available'],
    'Scheduled maintenance blocks vehicles',
);
check_tool(
    assistant_availability('vehicles', ['search' => '', 'status' => 'all'])['live'],
    'No dates checks current status',
);
check_tool(
    assistant_availability('vehicles', ['start_datetime' => $lookup['start_datetime']])[
        'needs_dates'
    ],
    'Incomplete intervals ask for both dates',
);
$current = booking_validate(['destination' => 'Baler'])['draft'];
$reply = assistant_resolve(
    [
        'intent' => 'system',
        'topic' => 'approval',
        'lookup' => [],
        'draft' => ['destination' => 'Invented'],
        'reply' => 'Invented answer',
    ],
    $current,
);
check_tool(
    $reply['draft']['destination'] === 'Baler' &&
        !$reply['ready'] &&
        str_contains($reply['reply'], 'Only the Administrator'),
    'System questions preserve booking progress and use verified permissions',
);
check_tool(
    str_contains(assistant_help('authenticator'), 'My profile'),
    'Authenticator help explains real profile controls',
);
$greeting = assistant_resolve(assistant_local_reply('Hello!', $current), $current);
check_tool(
    $greeting['intent'] === 'conversation' &&
        $greeting['draft'] === $current &&
        str_contains($greeting['reply'], 'Hello!'),
    'Greeting works locally and preserves draft',
);
$personnelHelp = assistant_resolve(
    assistant_local_reply('How do I request personnel?', $current),
    $current,
);
check_tool(
    str_contains($personnelHelp['reply'], 'Personnel requisitions') &&
        $personnelHelp['draft'] === $current,
    'Personnel help gives correct form and preserves vehicle draft',
);
$thanks = assistant_resolve(assistant_local_reply('Salamat po!', $current), $current);
check_tool(
    str_contains($thanks['reply'], 'welcome') && $thanks['draft'] === $current,
    'Thanks does not restart booking',
);
check_tool(
    assistant_local_reply('Change my destination to Manila', $current) === null,
    'Booking corrections continue to AI extraction',
);
check_tool(
    str_contains(assistant_help('reminders'), 'Unfinished work stays visible'),
    'Help describes persistent overview reminders',
);
require __DIR__ . '/../includes/url-security.php';
function auth_key(): string
{
    return str_repeat('t', 32);
}
$user = one('SELECT * FROM users WHERE id=3');
$hidden = assistant_track(['search' => 'SECRET-TRIP']);
check_tool(
    !$hidden['items'] && !str_contains($hidden['reply'], 'PRIVATE'),
    'Tracking hides another requester records',
);
$user = one('SELECT * FROM users WHERE id=1');
$found = assistant_track(['search' => 'SECRET-TRIP']);
check_tool(
    count($found['items']) === 1 &&
        $found['items'][0]['status'] === 'Completed' &&
        str_contains($found['items'][0]['url'], 'v1_'),
    'Authorized tracking returns live status and protected link',
);
run("UPDATE requisitions SET status='Approved' WHERE reference='SECRET-TRIP'");
$follow = assistant_track([], 'SECRET-TRIP');
check_tool(
    $follow['items'][0]['status'] === 'Approved',
    'Follow-up reloads current database status',
);
$user = one('SELECT * FROM users WHERE id=3');
check_tool(
    !assistant_track([], 'SECRET-TRIP')['items'],
    'Remembered search cannot bypass authorization',
);
run(
    "INSERT INTO personnel_bookings(reference,requester_id,office_id,requested_role,destination,purpose,start_datetime,end_datetime,status,created_at) VALUES('PR-2026-123',3,1,'Utility','Test tracking place','Work','2026-09-17 10:00:00','2026-09-17 11:00:00','Approved','2026-09-17 09:00:00')",
);
check_tool(
    assistant_track(['search' => 'PR-2026-123'])['items'][0]['kind'] === 'Personnel',
    'Tracking includes authorized personnel requests',
);
check_tool(
    assistant_track([])['items'] === [],
    'Ambiguous first tracking question requests a reference',
);
