<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UserRole: string
{
    use HasValues;

    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case HR_STAFF = 'hr_staff';
    case WAREHOUSE_STAFF = 'warehouse_staff';
}
