<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InternalBorrowStatus: string
{
    use HasValues;

    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case BORROWING = 'BORROWING';
    case PARTIALLY_RETURNED = 'PARTIALLY_RETURNED';
    case RETURNED = 'RETURNED';
    case OVERDUE = 'OVERDUE';
    case CANCELLED = 'CANCELLED';
}