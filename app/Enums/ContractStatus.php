<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ContractStatus: string
{
    use HasValues;

    case ACTIVE = 'ACTIVE';                     // Đang có hiệu lực
    case EXPIRED = 'EXPIRED';                   // Đã hết hạn
    case TERMINATED = 'TERMINATED';             // Chấm dứt trước hạn
    case RENEWED = 'RENEWED';                   // Đã gia hạn
}
