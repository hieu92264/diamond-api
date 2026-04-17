<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Department: string
{
    use HasValues;

        //Hội đồng Quản trị
    case BOARD_OF_DIRECTORS = 'board_of_directors';

        //Phòng Kế toán - Hành chính
    case ACCOUNTING_ADMIN = 'accounting_admin';

        //Phòng Nhân sự
    case HR = 'HR';

        //Phòng Tổ chức Sự kiện
    case EVENT_ORGANIZATION = 'event_organization';

        //Phòng Kỹ thuật
    case TECHNICAL = 'technical';
}
