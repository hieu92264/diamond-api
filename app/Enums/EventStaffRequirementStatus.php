<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EventStaffRequirementStatus: string
{
    use HasValues;

    case OPEN = 'open';
    case PARTIALLY_FILLED = 'partially_filled';
    case FILLED = 'filled';
    case CANCELLED = 'cancelled';
}
