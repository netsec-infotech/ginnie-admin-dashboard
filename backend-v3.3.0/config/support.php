<?php

use App\Enums\WeekDayEnum;

$driver = env('BROADCAST_CONNECTION', 'pusher');

if (in_array($driver, ['reverb', 'pusher'], true) && ! class_exists(\Pusher\Pusher::class)) {
    $driver = 'null';
}

return [
    // Support operating schedule and away message configuration
    'availability' => [
        'enabled' => true,
        'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
        'hours' => array_fill_keys(WeekDayEnum::values(), ['start_time' => '09:00', 'end_time' => '18:00']),
        'message' => 'Thanks for contacting us. Our support team is currently unavailable. We’ll reply as soon as support hours resume.',
    ],
    // Fallback polling configuration when WebSockets are disconnected
    'poll_interval_ms' => (int) env('SUPPORT_POLL_INTERVAL_MS', 15000),
    'polling_enabled' => true,
    'late_reply_minutes' => 15,
    'late_reply_options' => [5, 10, 15, 30, 60, 120],
    // Support chat attachments are public so previews can use normal storage URLs.
    'attachments_disk' => env('SUPPORT_ATTACHMENTS_DISK', 'public'),
    // Public real-time client credentials exposed to frontend and mobile
    'public_realtime' => [
        'driver' => $driver,
        'key' => $driver === 'pusher' ? env('PUSHER_APP_KEY') : ($driver === 'reverb' ? env('REVERB_APP_KEY') : ''),
        'host' => $driver === 'pusher' ? env('PUSHER_HOST') : ($driver === 'reverb' ? env('REVERB_HOST') : null),
        'port' => $driver === 'pusher' ? (int) env('PUSHER_PORT', 443) : ($driver === 'reverb' ? (int) env('REVERB_PORT', 443) : null),
        'scheme' => $driver === 'pusher' ? env('PUSHER_SCHEME', 'https') : ($driver === 'reverb' ? env('REVERB_SCHEME', 'https') : null),
        'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
        'auth_endpoint' => rtrim((string) env('APP_URL'), '/').'/api/broadcasting/auth',
    ],
];
