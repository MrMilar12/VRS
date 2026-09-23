<?php
// This conversational route is read-only and uses a separate history from booking drafts.
auth_limit('tracking-chat', (string) $user['id'], 40, 3600);
$message = field('message', 3000);
$state = $_SESSION['tracking_chat'] ?? [];
if (($state['expires'] ?? 0) < time()) {
    $state = [];
}
$messages = $state['messages'] ?? [];
$messages[] = ['role' => 'user', 'content' => $message];
$messages = array_slice($messages, -10);
$local = assistant_local_reply($message, []);
if ($local !== null) {
    $result = ['reply' => $local['reply'], 'items' => [], 'checked_at' => date('Y-m-d H:i:s')];
    $messages[] = ['role' => 'assistant', 'content' => $result['reply']];
    $_SESSION['tracking_chat'] = [
        'messages' => $messages,
        'search' => $state['search'] ?? '',
        'expires' => time() + 1800,
    ];
    echo json_encode($result, JSON_THROW_ON_ERROR);
    return;
}
$lookup = assistant_tracking_lookup($message, $state['search'] ?? '');
if ($lookup === null) {
    $instruction = [
        'role' => 'user',
        'content' =>
            'Tracking-only conversation: interpret the following question as a read-only tracking lookup. Return tracking intent and extract the reference, person, destination or status into lookup.search. For a follow-up reuse the previous search. If unclear use null search and ask for a reference. Do not prepare or alter a booking. Previous search: ' .
            json_encode($state['search'] ?? '') .
            '. Question: ' .
            $message,
    ];
    $input = $messages;
    array_pop($input);
    $input[] = $instruction;
    $parsed = booking_respond($input, []);
    $lookup = $parsed['intent'] === 'tracking' ? $parsed['lookup'] : ['search' => ''];
}
$result = assistant_track($lookup, $state['search'] ?? '');
$messages[] = ['role' => 'assistant', 'content' => $result['reply']];
$_SESSION['tracking_chat'] = [
    'messages' => $messages,
    'search' => $result['search'],
    'expires' => time() + 1800,
];
echo json_encode($result, JSON_THROW_ON_ERROR);
