<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ContractStatus: string
{
    use HasValues;

    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case TERMINATED = 'terminated';
}
