<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ItemStatus: string
{
    use HasValues;

    case AVAILABLE = 'available';
    case UNAVAILABLE = 'unavailable';
    case DISPOSED = 'disposed';
}
