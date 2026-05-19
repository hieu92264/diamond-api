<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MaintenanceType: string
{
    use HasValues;

    case REPAIR = 'REPAIR';
    case LAUNDRY = 'LAUNDRY';
    case REPLACEMENT = 'REPLACEMENT';
}
