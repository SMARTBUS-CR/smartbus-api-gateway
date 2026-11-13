<?php

use App\Enums\Services;

it('defines documentation metadata for every service', function () {
    expect([
        Services::AUTH->value => [Services::AUTH_LABEL, Services::AUTH_DESCRIPTION, Services::AUTH_WEIGHT],
        Services::GPS->value => [Services::GPS_LABEL, Services::GPS_DESCRIPTION, Services::GPS_WEIGHT],
        Services::ETA->value => [Services::ETA_LABEL, Services::ETA_DESCRIPTION, Services::ETA_WEIGHT],
    ])->toBe([
        'auth' => [
            'Authentication Service',
            'This group contains endpoints that interact with the Authentication microservice. <br>These endpoints handle user registration, login, and logout operations by forwarding requests to the Auth microservice.<br><br>For more information about the Auth microservice, see the [SmartBus Authentication](https://smartbus-authentication.onrender.com/) documentation.',
            2,
        ],
        'gps' => [
            'GPS Tracking Service',
            'This group contains endpoints that interact with the GPS microservice. <br>These endpoints handle GPS location data and related operations by forwarding requests to the GPS microservice.<br><br>For more information about the GPS microservice, see the [SmartBus GPS Tracking](https://smartbus-gps-tracking.onrender.com/) documentation.',
            7,
        ],
        'eta' => [
            'ETA Calculation Service',
            'Calculates estimated time of arrival for vehicles based on GPS data.',
            3,
        ],
    ]);
});
