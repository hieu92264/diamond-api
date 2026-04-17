<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WarehouseType: string
{
    use HasValues;

    case COSTUME = 'COSTUME';   // Kho Trang phục
    case PROP = 'PROP';         // Kho Đạo cụ
    case EQUIPMENT = 'EQUIPMENT'; // Kho Thiết bị (Âm thanh, ánh sáng, máy móc)
    case GENERAL = 'GENERAL';   // Kho tổng hợp
    case CONSUMABLE = 'CONSUMABLE';// Kho vật tư tiêu hao (Băng keo, pin, nước uống...)
    case DEFAULT = 'UNKNOWN';
}
