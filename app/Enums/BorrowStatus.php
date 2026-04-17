<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum BorrowStatus: string
{
    use HasValues;

    case PENDING = 'PENDING';     // Đang chờ duyệt
    case APPROVED = 'APPROVED';   // Đã duyệt
    case REJECTED = 'REJECTED';   // Bị từ chối
    case RETURNED = 'RETURNED';   // Đã trả
    case EXPIRED = 'EXPIRED'; // Quá hạn (chưa trả sau ngày dự kiến trả)
}
