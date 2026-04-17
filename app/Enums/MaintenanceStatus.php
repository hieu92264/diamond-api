<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MaintenanceStatus: string
{
    use HasValues;

    case OPEN = 'OPEN';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
}