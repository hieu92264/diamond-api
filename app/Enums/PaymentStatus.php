<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentStatus: string
{
    use HasValues;

    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
}
