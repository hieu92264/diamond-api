<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Position: string
{
    use HasValues;

    case SINGER = 'Singer';
    case MC = 'MC';
    case RAPPER = 'Rapper';
    case DANCER = 'Dancer';
    case CHOREOGRAPHER = 'Chorographer'; //Biên đạo múa/ nhảy
    case LOGISTICS_STAFF = 'Logistics Staff'; //Nhân viên hậu cần
    case SOUND_TECHNICIAN = 'Sound Technician';
    case LIGHTING_TECHNICIAN = 'Lighting Technician';
    case WAREHOUSE_STAFF = 'Warehouse Staff';
    case OFFICE_STAFF = 'Office Staff';
}
