<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ItemCategoryType: string
{
    use HasValues;

    case COSTUME = 'costume';
    case PROP = 'prop';
    case EQUIPMENT = 'equipment';
}
