<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Gender: string
{
    use HasValues;

    case MALE = 'male';
    case FEMALE = 'female';
    case OTHER = 'other';
}
