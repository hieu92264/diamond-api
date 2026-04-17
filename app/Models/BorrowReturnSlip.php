<?php

namespace App\Models;

use App\Enums\BorrowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BorrowReturnSlip extends Model
{
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
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'borrow_date' => 'date',
            'due_date' => 'date',
            'return_date' => 'date',
            'status' => BorrowStatus::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus(Builder $query, BorrowStatus|string $status): Builder
    {
        return $query->where(
            'status',
            $status instanceof BorrowStatus ? $status->value : $status
        );
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereDate('due_date', '<', today())
            ->where('status', '!=', BorrowStatus::RETURNED->value);
    }
}
