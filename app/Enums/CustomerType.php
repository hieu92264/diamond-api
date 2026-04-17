<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CustomerType: string
{
    use HasValues;

    case INDIVIDUAL = 'INDIVIDUAL';
    case COMPANY = 'COMPANY';
}