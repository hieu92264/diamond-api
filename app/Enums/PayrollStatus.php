<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PayrollStatus: string
{
    use HasValues;

    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}
