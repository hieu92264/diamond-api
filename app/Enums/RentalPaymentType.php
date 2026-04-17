<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RentalPaymentType: string
{
    use HasValues;

    case DEPOSIT = 'DEPOSIT';
    case RENTAL_PAYMENT = 'RENTAL_PAYMENT';
    case COMPENSATION = 'COMPENSATION';
    case REFUND_DEPOSIT = 'REFUND_DEPOSIT';
    case OTHER = 'OTHER';
}