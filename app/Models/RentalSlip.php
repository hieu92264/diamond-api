<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalSlip extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'customer_id',
        'customer_name',
        'customer_phone',
        'warehouse_id',
        'rental_date',
        'start_date',
        'due_date',
        'return_date',
        'deposit_amount',
        'total_rental_amount',
        'total_compensation_amount',
        'total_discount_amount',
        'final_amount',
        'paid_amount',
        'remaining_amount',
        'payment_status',
        'status',
        'approved_user_id',
        'approved_at',
        'created_by',
        'terms_and_conditions',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rental_date' => 'date',
            'start_date' => 'date',
            'due_date' => 'date',
            'return_date' => 'date',
            'deposit_amount' => 'decimal:2',
            'total_rental_amount' => 'decimal:2',
            'total_compensation_amount' => 'decimal:2',
            'total_discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'payment_status' => PaymentStatus::class,
            'status' => RentalStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus(Builder $query, RentalStatus|string $status): Builder
    {
        $value = $status instanceof RentalStatus ? $status->value : strtoupper($status);

        return $query->where('status', $value);
    }

    public function scopePaymentStatus(Builder $query, PaymentStatus|string $status): Builder
    {
        $value = $status instanceof PaymentStatus ? $status->value : strtoupper($status);

        return $query->where('payment_status', $value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', [
                RentalStatus::RETURNED->value,
                RentalStatus::CANCELLED->value,
                RentalStatus::CLOSED->value,
            ]);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_user_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(RentalDetail::class, 'rental_slip_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class, 'rental_slip_id', 'id');
    }
}
