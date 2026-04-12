<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ItemConditionStatus: string
{
    use HasValues;

    case GOOD = 'good';
    case FAIR = 'fair';
    case DAMAGED = 'damaged';
    case MAINTENANCE = 'maintenance';
}
