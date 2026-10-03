<?php

namespace App\Enums;

enum Services: string
{
    public const AUTH_LABEL = 'Authentication Service';

    public const AUTH_DESCRIPTION = 'This group contains endpoints that interact with the Authentication microservice. <br>These endpoints handle user registration, login, and logout operations by forwarding requests to the Auth microservice.<br><br>For more information about the Auth microservice, see the [SmartBus Authentication](https://smartbus-authentication.onrender.com/) documentation.';

    public const AUTH_WEIGHT = 2;

    public const GPS_LABEL = 'GPS Tracking Service';

    public const GPS_DESCRIPTION = 'This group contains endpoints that interact with the GPS microservice. <br>These endpoints handle GPS location data and related operations by forwarding requests to the GPS microservice.<br><br>For more information about the GPS microservice, see the [SmartBus GPS Tracking](https://smartbus-gps-tracking.onrender.com/) documentation.';

    public const GPS_WEIGHT = 7;

    public const ETA_LABEL = 'ETA Calculation Service';

    public const ETA_DESCRIPTION = 'Calculates estimated time of arrival for vehicles based on GPS data.';

    public const ETA_WEIGHT = 3;

    case AUTH = 'auth';
    case GPS = 'gps';
    case ETA = 'eta';
}
