<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RentalPaymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RentalPayment extends Model
{
    protected $fillable = [
        'is_active',
        'rental_slip_id',
        'payment_type',
        'payment_date',
        'amount',
        'payment_method',
        'reference_no',
        'received_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'payment_type' => RentalPaymentType::class,
            'payment_date' => 'datetime',
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePaymentType(Builder $query, RentalPaymentType|string $type): Builder
    {
        $value = $type instanceof RentalPaymentType ? $type->value : strtoupper($type);

        return $query->where('payment_type', $value);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('payment_date');
    }
}