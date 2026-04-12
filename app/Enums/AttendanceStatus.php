<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum AttendanceStatus: string
{
    use HasValues;

    case ASSIGNED = 'assigned';
    case PRESENT = 'present';
    case ABSENT = 'absent';
    case LATE = 'late';
    case CANCELLED = 'cancelled';
}
