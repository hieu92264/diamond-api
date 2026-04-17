<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Work: string
{
    use HasValues;

    case PROBATION = 'PROBATION';
    case ON_LEAVE = 'ON_LEAVE';
    case OFFICIAL = 'OFFICIAL';
    case TERMINATED = 'TERMINATED';
}