<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InventoryTransactionStatus: string
{
    use HasValues;

    case POSTED = 'posted';
    case CANCELLED = 'cancelled';
}
