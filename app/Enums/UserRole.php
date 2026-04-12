<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UserRole: string
{
    use HasValues;

    case SYSTEM_ADMIN = 'system_admin';
    case EVENT_OPERATOR = 'event_operator';
    case HR_PAYROLL = 'hr_payroll';
    case WAREHOUSE_STAFF = 'warehouse_staff';
    case ACCOUNTING = 'accounting';
    case EMPLOYEE_PORTAL = 'employee_portal';
    case CUSTOMER_PORTAL = 'customer_portal';
}
