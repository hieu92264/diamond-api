<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ContractType: string
{
    use HasValues;

    case OFFICIAL = 'official';
    case SEASONAL = 'seasonal';
    case FREELANCE = 'freelance';
    case COLLABORATOR = 'collaborator';
}
