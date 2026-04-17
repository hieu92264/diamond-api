<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Position: string
{
    use HasValues;

    case SINGER = 'SINGER';
    case MC = 'MC';
    case RAPPER = 'RAPPER';
    case DANCER = 'DANCER';
    case CHOREOGRAPHER = 'CHOREOGRAPHER';
    case LOGISTICS_STAFF = 'LOGISTICS_STAFF';
    case SOUND_TECHNICIAN = 'SOUND_TECHNICIAN';
    case LIGHTING_TECHNICIAN = 'LIGHTING_TECHNICIAN';
    case WAREHOUSE_STAFF = 'WAREHOUSE_STAFF';
    case OFFICE_STAFF = 'OFFICE_STAFF';
}