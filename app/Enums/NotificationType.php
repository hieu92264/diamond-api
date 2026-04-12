<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum NotificationType: string
{
    use HasValues;

    case EVENT_REMINDER = 'event_reminder';
    case RETURN_REMINDER = 'return_reminder';
    case PAYROLL = 'payroll';
    case GENERAL = 'general';
}
