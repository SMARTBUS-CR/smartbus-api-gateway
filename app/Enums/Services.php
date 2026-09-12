<?php

namespace App\Enums;

enum Services: string
{
    case AUTH = 'auth';
    case GPS = 'gps';
    case ETA = 'eta';
}
