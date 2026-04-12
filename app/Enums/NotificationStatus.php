<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum NotificationStatus: string
{
    use HasValues;

    case SENT = 'sent';
    case READ = 'read';
    case CANCELLED = 'cancelled';
}
