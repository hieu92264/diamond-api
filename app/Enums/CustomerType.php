<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CustomerType: string
{
    use HasValues;

    case COMPANY = 'company';
    case INDIVIDUAL = 'individual';
}
