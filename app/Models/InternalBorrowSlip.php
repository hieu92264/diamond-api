<?php

namespace App\Models;

use App\Enums\InternalBorrowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InternalBorrowSlip extends Model
{
    protected $table = 'internal_borrow_slips';

    protected $fillable = [
        'is_active',
        'code',
        'employee_id',
        'employee_name',
        'warehouse_id',
        'borrow_date',
        'due_date',
        'return_date',
        'status',
        'approved_user_id',
        'approved_at',
        'rejected_user_id',
        'rejected_at',
        'created_by',
        'purpose',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'borrow_date' => 'date',
            'due_date' => 'date',
            'return_date' => 'date',
            'status' => InternalBorrowStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus(Builder $query, InternalBorrowStatus|string $status): Builder
    {
        $value = $status instanceof InternalBorrowStatus ? $status->value : strtoupper($status);

        return $query->where('status', $value);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereDate('due_date', '<', today())
            ->whereNotIn('status', [
                InternalBorrowStatus::RETURNED->value,
                InternalBorrowStatus::CANCELLED->value,
            ]);
    }
}