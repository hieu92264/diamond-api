<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InventoryTransactionType: string
{
    use HasValues;

    case IMPORT = 'import';
    case EXPORT = 'export';
    case ADJUSTMENT = 'adjustment';
    case MAINTENANCE_OUT = 'maintenance_out';
    case MAINTENANCE_IN = 'maintenance_in';
    case LOSS = 'loss';
}
