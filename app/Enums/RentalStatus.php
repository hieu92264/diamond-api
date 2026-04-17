<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RentalStatus: string
{
    use HasValues;

    case DRAFT = 'DRAFT';
    case PENDING_APPROVAL = 'PENDING_APPROVAL';
    case APPROVED = 'APPROVED';
    case ACTIVE = 'ACTIVE';
    case PARTIALLY_RETURNED = 'PARTIALLY_RETURNED';
    case RETURNED = 'RETURNED';
    case OVERDUE = 'OVERDUE';
    case CANCELLED = 'CANCELLED';
    case CLOSED = 'CLOSED';
}