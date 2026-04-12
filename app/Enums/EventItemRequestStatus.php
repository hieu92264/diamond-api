<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EventItemRequestStatus: string
{
    use HasValues;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case BORROWED = 'borrowed';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';
}
