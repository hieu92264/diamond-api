<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CustomerStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLACKLISTED = 'blacklisted';
}
