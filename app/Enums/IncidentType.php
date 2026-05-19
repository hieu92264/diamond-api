<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum IncidentType: string
{
    use HasValues;

    case DAMAGED = 'DAMAGED';
    case LOST = 'LOST';
    case OTHER = 'OTHER';
}
