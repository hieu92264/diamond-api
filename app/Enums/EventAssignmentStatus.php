<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EventAssignmentStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
