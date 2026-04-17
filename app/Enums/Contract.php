<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Contract: string
{
    use HasValues;

    case FULL_TIME  = 'Full-time';
    case PART_TIME  = 'Part-time';
    case INTERN = 'Intern';
    case COLLABORATOR = 'Collaborator';
    case SEASONAL = 'Seasonal';
}
