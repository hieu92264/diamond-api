<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum IncidentStatus: string
{
    use HasValues;

    case OPEN = 'OPEN';
    case PROCESSING = 'PROCESSING';
    case RESOLVED = 'RESOLVED';
    case CLOSED = 'CLOSED';
}