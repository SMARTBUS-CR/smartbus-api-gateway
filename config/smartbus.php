<?php

return [
    'auth' => [
        'url' => env('AUTH_SERVICE_URL', 'http://localhost:8000'),
    ],
    'gps' => [
        'url' => env('GPS_SERVICE_URL', 'http://localhost:8001'),
    ],
    'eta' => [
        'url' => env('ETA_SERVICE_URL', 'http://localhost:8002'),
    ],
];
