<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ContractStatus: string
{
    use HasValues;

    case ACTIVE = 'ACTIVE';
    case EXPIRED = 'EXPIRED';
    case TERMINATED = 'TERMINATED';
    case RENEWED = 'RENEWED';
}