<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EventStatus: string
{
    use HasValues;

    case DRAFT = 'draft';
    case QUOTED = 'quoted';
    case CONFIRMED = 'confirmed';
    case PREPARING = 'preparing';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
