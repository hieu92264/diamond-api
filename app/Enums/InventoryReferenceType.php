<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InventoryReferenceType: string
{
    use HasValues;

    case INTERNAL_BORROW_SLIP = 'INTERNAL_BORROW_SLIP';
    case RENTAL_SLIP = 'RENTAL_SLIP';
    case INTERNAL_INCIDENT = 'INTERNAL_INCIDENT';
    case RENTAL_INCIDENT = 'RENTAL_INCIDENT';
    case MAINTENANCE_TICKET = 'MAINTENANCE_TICKET';
    case MANUAL_ADJUSTMENT = 'MANUAL_ADJUSTMENT';
    case OTHER = 'OTHER';
}