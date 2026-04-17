<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Contract: string
{
    use HasValues;

    case FULL_TIME = 'FULL_TIME';
    case PART_TIME = 'PART_TIME';
    case INTERN = 'INTERN';
    case COLLABORATOR = 'COLLABORATOR';
    case SEASONAL = 'SEASONAL';
}