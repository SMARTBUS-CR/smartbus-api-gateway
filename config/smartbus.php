<?php

return [
    'auth' => [
        'url' => env('AUTH_SERVICE_URL', 'http://localhost:8000'),
        'token_cache_ttl' => (int) env('AUTH_TOKEN_CACHE_TTL', 30),
    ],
    'gps' => [
        'url' => env('GPS_SERVICE_URL', 'http://localhost:8001'),
    ],
    'eta' => [
        'url' => env('ETA_SERVICE_URL', 'http://localhost:8002'),
    ],
    'operations' => [
        'url' => env('OPERATIONS_SERVICE_URL', env('DASHBOARD_SERVICE_URL', 'http://localhost:8000')),
    ],
];
