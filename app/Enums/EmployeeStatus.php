<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EmployeeStatus: string
{
    use HasValues;

    case PROBATION = 'probation';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case QUIT = 'quit';
}
