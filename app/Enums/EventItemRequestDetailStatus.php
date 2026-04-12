<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EventItemRequestDetailStatus: string
{
    use HasValues;

    case NORMAL = 'normal';
    case LOST = 'lost';
    case DAMAGED = 'damaged';
}
