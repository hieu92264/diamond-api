<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PayrollType: string
{
    use HasValues;

    case MONTHLY = 'monthly';
    case PER_SHOW = 'per_show';
}
