<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Work: string
{
    use HasValues;
    case PROBATION = 'PROBATION'; //thử việc
    case ON_LEAVE = 'ON_LEAVE'; //nghỉ phép
    case OFFICIAL = 'OFFICIAL'; // Chính thức
    case TERMINATED = 'TERMINATED'; // nghỉ việc
}
