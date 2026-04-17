<?php

namespace  App\Enums;

enum EquipmentStatus: string
{
    case AVAILABLE = 'available';         // Tốt
    case DAMAGED = 'DAMAGED';   // Hỏng
    case MAINTENANCE = 'MAINTENANCE'; // Đang bảo trì
    case LOST = 'LOST';         // Mất
}
