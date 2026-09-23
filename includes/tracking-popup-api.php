<?php
// Questions from the header have their own conversation, separate from booking drafts.
auth_limit('header-assistant', (string) $user['id'], 40, 3600);
$message = field('message', 3000);
$state = $_SESSION['header_assistant'] ?? [];
if (($state['expires'] ?? 0) < time()) {
    $state = [];
}
$messages = $state['messages'] ?? [];
$messages[] = ['role' => 'user', 'content' => $message];
$messages = array_slice($messages, -12);
$lookup = assistant_tracking_lookup($message, $state['search'] ?? '');
$parsed =
    $lookup !== null
        ? ['intent' => 'tracking', 'lookup' => $lookup]
        : assistant_local_reply($message, []);
$modelMessages = array_map(
    fn($turn) => ['role' => $turn['role'], 'content' => $turn['content']],
    $messages,
);
$result = assistant_resolve($parsed ?? booking_respond($modelMessages, []), [], $state['search'] ?? '');
$openBooking = $result['intent'] === 'booking';
if ($openBooking) {
    $result['reply'] =
        'I can help you prepare that trip. Select Open Booking Assistant below to enter and review your booking details. Nothing has been submitted.';
}
$messages[] = ['role' => 'assistant', 'content' => $result['reply'], 'open_booking' => $openBooking];
$_SESSION['header_assistant'] = [
    'messages' => $messages,
    'search' => $result['tracking']['search'] ?? ($state['search'] ?? ''),
    'expires' => time() + 1800,
];
echo json_encode(
    [
        'reply' => $result['reply'],
        'open_booking' => $openBooking,
        'items' => $result['tracking']['items'] ?? [],
        'availability' => $result['availability'],
    ],
    JSON_THROW_ON_ERROR,
);
