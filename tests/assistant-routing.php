<?php
// Run with: php tests/assistant-routing.php
require __DIR__ . '/../includes/booking-assistant.php';
require __DIR__ . '/../includes/assistant-tools.php';
function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
foreach (
    [
        'What is the status of my vehicle request?',
        'Track my trip to Baler',
        'Book a van to Baler tomorrow',
    ]
    as $message
) {
    expect(
        assistant_local_reply($message, []) === null,
        'Natural-language request must reach AI: ' . $message,
    );
}
$config['timezone'] = 'Asia/Manila';
$user = ['full_name' => 'Test User'];
function requests(): array
{
    return [];
}
function personnel_bookings(): array
{
    return [];
}
function manage(): bool
{
    return false;
}
$welcome = assistant_local_reply('hello', []);
expect($welcome['intent'] === 'conversation', 'Greeting remains available locally.');
expect(
    str_contains($welcome['reply'], 'Asia/Manila') &&
        str_contains($welcome['reply'], '0 scheduled today'),
    'Greeting includes local time and scoped counts.',
);
expect(
    !str_contains($welcome['reply'], 'Open Reports & insights'),
    'Restricted reports not offered to requesters.',
);
expect(
    str_contains($welcome['reply'], 'Weather unavailable'),
    'No invented weather without location.',
);
$record = [
    'status' => 'Approved',
    'created_at' => '2026-09-01',
    'start_datetime' => '2026-09-19 09:00:00',
    'end_datetime' => '2026-09-19 17:00:00',
    'office_name' => 'Test office',
];
$summary = assistant_welcome_summary(
    [$record],
    [array_replace($record, ['status' => 'Completed'])],
    '2026-09-19 12:00:00',
);
expect(
    str_contains($summary, '2 scheduled today') && str_contains($summary, '1 completed, 1 open'),
    'Summary combines vehicle and personnel records.',
);
expect(
    assistant_tracking_lookup('Track VRS-2026-00001')['search'] === 'VRS-2026-00001',
    'Reference extracted.',
);
expect(
    assistant_tracking_lookup('Has it started?', 'VRS-2026-00001')['search'] === 'VRS-2026-00001',
    'Follow-up preserves reference.',
);
expect(
    assistant_tracking_lookup('Has it started?') === null,
    'No reference invented for a new conversation.',
);
expect(
    assistant_tracking_lookup('Track my trip to Baler') === null,
    'Destination question reaches AI.',
);
expect(
    assistant_lookup_validate(['search' => 'Baler', 'status' => null])['status'] === 'all',
    'Nullable AI status normalized.',
);
$tracking = [
    'intent' => 'tracking',
    'topic' => 'tracking',
    'lookup' => ['search' => 'Baler'],
    'reply' => 'Checking your trip.',
];
function parse_fixture(array $reply): array
{
    return booking_parse_response([
        'status' => 'completed',
        'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($reply)]]]],
    ]);
}
expect(
    parse_fixture($tracking)['lookup']['search'] === 'Baler',
    'Tracking does not require unrelated booking fields.',
);
$tracking['draft'] = ['vehicle_type' => 'not a vehicle', 'start_datetime' => 'tomorrow'];
expect(
    parse_fixture($tracking)['draft']['vehicle_type'] === '',
    'Read-only replies ignore irrelevant draft data.',
);
try {
    parse_fixture([
        'intent' => 'booking',
        'topic' => 'booking',
        'lookup' => [],
        'reply' => 'Plan a trip',
    ]);
    throw new LogicException('Missing booking draft accepted.');
} catch (BookingResponseFormatException $expected) {
}
echo "Assistant routing and response checks passed.\n";
// Exercise the actual tracking route: greetings must not call the AI provider
// or discard the reference used by the next tracking follow-up.
function auth_limit(...$args): void {}
function field(string $name, int $max): string
{
    return $_POST[$name];
}
$user['id'] = 1;
$_POST = ['message' => 'hello', 'weather_location_error' => 'denied'];
$_SESSION['tracking_chat'] = ['search' => 'VRS-2026-00001', 'expires' => time() + 60];
ob_start();
require __DIR__ . '/../includes/tracking-chat-api.php';
$response = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
expect(
    str_contains($response['reply'], 'location permission was denied'),
    'Tracking greeting explains denied location.',
);
expect(
    str_contains($response['reply'], 'This month’s report'),
    'Tracking greeting contains analytics.',
);
expect(
    $_SESSION['tracking_chat']['search'] === 'VRS-2026-00001',
    'Greeting preserves tracking context.',
);
echo "Tracking greeting endpoint checks passed.\n";
