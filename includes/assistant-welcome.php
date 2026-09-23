<?php
require_once __DIR__ . '/dashboard-analytics.php';

function assistant_welcome_summary(array $vehicles, array $personnel, string $now): string
{
    $stats = dashboard_analytics($vehicles, $personnel, 'all', '1', $now);
    $today = substr($now, 0, 10);
    $scheduled = 0;
    $active = 0;
    $pending = 0;
    foreach ([...$vehicles, ...$personnel] as $record) {
        $status = $record['status'];
        if (
            in_array(
                $status,
                ['Approved', 'Dispatched', 'In Progress', 'Returned', 'Completed'],
                true,
            ) &&
            substr($record['start_datetime'], 0, 10) <= $today &&
            substr($record['end_datetime'], 0, 10) >= $today
        ) {
            $scheduled++;
        }
        if (in_array($status, ['Dispatched', 'In Progress'], true)) {
            $active++;
        }
        if (in_array($status, ['Pending Supervisor', 'Pending Administrative Approval'], true)) {
            $pending++;
        }
    }
    return "Your accessible records: $scheduled scheduled today, $active currently in progress, and $pending awaiting approval.\nThis month’s report (by creation date): {$stats['total']} requests — {$stats['vehicle']} vehicle and {$stats['personnel']} personnel; {$stats['completed']} completed, {$stats['open']} open.";
}

function assistant_welcome_reply(): string
{
    global $user, $config;
    $now = new DateTimeImmutable('now', new DateTimeZone($config['timezone']));
    $hour = (int) $now->format('G');
    $greeting = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');
    $name = explode(' ', trim($user['full_name']))[0];
    return "Good $greeting, $name! It’s " .
        $now->format('l, F j, Y · g:i A') .
        " ({$config['timezone']}).\n\n" .
        assistant_welcome_weather() .
        "\n\n" .
        assistant_welcome_summary(requests(), personnel_bookings(), $now->format('Y-m-d H:i:s')) .
        "\n\n" .
        (manage()
            ? 'Open Reports & insights for detailed system reports.'
            : 'Open Overview for analytics and Requisitions for your request details.') .
        ' How can I help today?';
}

function assistant_welcome_weather(): string
{
    $lat = $_POST['weather_latitude'] ?? null;
    $lon = $_POST['weather_longitude'] ?? null;
    $label = 'your current location';
    if (
        !is_numeric($lat) ||
        !is_numeric($lon) ||
        abs((float) $lat) > 90 ||
        abs((float) $lon) > 180
    ) {
        return match ($_POST['weather_location_error'] ?? '') {
            'insecure'
                => 'Weather unavailable: browser location requires HTTPS or localhost. Open this site over HTTPS, then say hello again.',
            'denied'
                => 'Weather unavailable: location permission was denied. Allow location for this site in your browser settings, then say hello again.',
            'timeout'
                => 'Weather unavailable: location detection timed out. Respond to the location prompt and say hello again.',
            'unsupported'
                => 'Weather unavailable: this browser does not support location detection.',
            'unavailable'
                => 'Weather unavailable: your device could not determine its location. Check location services and try again.',
            default
                => 'Weather unavailable: allow location access in your browser, then say hello again.',
        };
    }
    $key = hash('sha256', json_encode([$lat, $lon, $label]));
    $cached = $_SESSION['assistant_weather'] ?? [];
    if (($cached['key'] ?? '') === $key && ($cached['expires'] ?? 0) > time()) {
        return $cached['text'];
    }
    $text = "Weather for $label is temporarily unavailable.";
    if (function_exists('curl_init')) {
        $url =
            'https://api.open-meteo.com/v1/forecast?' .
            http_build_query([
                'latitude' => $lat,
                'longitude' => $lon,
                'current' => 'temperature_2m,weather_code',
                'timezone' => 'auto',
            ]);
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $data = is_string($body) ? json_decode($body, true) : null;
        $current = $data['current'] ?? [];
        if (
            $status === 200 &&
            is_numeric($current['temperature_2m'] ?? null) &&
            is_numeric($current['weather_code'] ?? null) &&
            is_string($current['time'] ?? null)
        ) {
            $code = (int) $current['weather_code'];
            $condition = match (true) {
                $code === 0 => 'clear sky',
                $code <= 3 => 'partly cloudy to overcast',
                in_array($code, [45, 48]) => 'fog',
                in_array($code, [51, 53, 55, 56, 57]) => 'drizzle',
                in_array($code, [61, 63, 65, 66, 67, 80, 81, 82]) => 'rain',
                in_array($code, [71, 73, 75, 77, 85, 86]) => 'snow',
                in_array($code, [95, 96, 99]) => 'thunderstorms',
                default => 'conditions unavailable',
            };
            $text =
                "Weather for $label: {$current['temperature_2m']} °C, $condition. Updated " .
                str_replace('T', ' ', $current['time']) .
                ' (local weather time; Open-Meteo).';
        }
    }
    $_SESSION['assistant_weather'] = [
        'key' => $key,
        'expires' => time() + (($status ?? 0) === 200 ? 600 : 60),
        'text' => $text,
    ];
    return $text;
}
