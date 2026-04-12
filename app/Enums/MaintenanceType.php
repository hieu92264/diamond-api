<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MaintenanceType: string
{
    use HasValues;

    case REPAIR = 'repair';
    case LAUNDRY = 'laundry';
    case REPLACEMENT = 'replacement';
}
