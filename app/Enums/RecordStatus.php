<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RecordStatus: string
{
    use HasValues;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}
