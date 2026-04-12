<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MaintenanceResultStatus: string
{
    use HasValues;

    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
