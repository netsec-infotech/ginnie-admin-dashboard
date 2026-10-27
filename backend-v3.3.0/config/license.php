<?php

return [
    // License validation endpoint
    'endpoint' => env('LICENSE_ENDPOINT', 'https://validator-new.infinitietech.com/api/v1/validate'),

    // License deregistration endpoint
    'deregister_endpoint' => env('LICENSE_DEREGISTER_ENDPOINT', 'https://validator-new.infinitietech.com/api/v1/deregister'),

    // API Key for license validator
    'api_key' => env('LICENSE_API_KEY', '554c9a4319fd81f75586008b347f767eb9a48b6995c26b50'),

    // How often to revalidate the license with the remote server (in minutes)
    'recheck_minutes' => (int) env('LICENSE_RECHECK_MINUTES', 720), // default 12 hours

    // HTTP timeouts (seconds). Short so an unreachable validator never hangs a page.
    'timeout' => (float) env('LICENSE_TIMEOUT', 5),
    'connect_timeout' => (float) env('LICENSE_CONNECT_TIMEOUT', 5),

    'unavailable_retry_minutes' => (int) env('LICENSE_UNAVAILABLE_RETRY_MINUTES', 60),

    'allow_offline_activation' => (bool) env('LICENSE_ALLOW_OFFLINE_ACTIVATION', true),

    'item_id' => env('LICENSE_ITEM_ID', '61119699'),
];
