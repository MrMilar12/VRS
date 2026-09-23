<?php
function destination_schema(PDO $db): void
{
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        return;
    }
    foreach (['requisitions', 'personnel_bookings'] as $table) {
        $column = $db
            ->query("SHOW COLUMNS FROM $table LIKE 'destination'")
            ->fetch(PDO::FETCH_ASSOC);
        if (
            $column &&
            !in_array(strtolower($column['Type']), ['text', 'mediumtext', 'longtext'], true)
        ) {
            $db->exec("ALTER TABLE $table MODIFY destination TEXT NOT NULL");
        }
    }
}
function destination_value(): string
{
    if (!array_key_exists('destinations', $_POST)) {
        return field('destination', 5000);
    }
    $places = $_POST['destinations'];
    if (!is_array($places) || !$places || count($places) > 20) {
        throw new RuntimeException('Enter between 1 and 20 places.');
    }
    $clean = [];
    foreach ($places as $place) {
        if (
            !is_string($place) ||
            trim($place) === '' ||
            mb_strlen(trim($place)) > 255 ||
            preg_match('/[\r\n]/', $place)
        ) {
            throw new RuntimeException(
                'Enter a valid place in each destination row (up to 255 characters).',
            );
        }
        $clean[] = trim($place);
    }
    $value = implode("\n", $clean);
    if (mb_strlen($value) > 5000) {
        throw new RuntimeException('The combined places must be 5,000 characters or fewer.');
    }
    return $value;
}
function destination_inputs(array $record): array
{
    $places =
        $record['destinations'] ?? preg_split('/\R/', (string) ($record['destination'] ?? ''));
    if (!is_array($places)) {
        return [''];
    }
    return array_values(
        array_map(fn($place) => is_string($place) ? $place : '', array_slice($places, 0, 20)),
    ) ?: [''];
}
