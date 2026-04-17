<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WarehouseType: string
{
    use HasValues;

    case COSTUME = 'COSTUME';
    case PROP = 'PROP';
    case EQUIPMENT = 'EQUIPMENT';
    case GENERAL = 'GENERAL';
    case CONSUMABLE = 'CONSUMABLE';
    case UNKNOWN = 'UNKNOWN';
}