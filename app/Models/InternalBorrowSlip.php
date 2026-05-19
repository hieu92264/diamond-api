<?php

namespace App\Models;

use App\Enums\InternalBorrowStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_user_id', 'id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_user_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(InternalBorrowDetail::class, 'internal_borrow_slip_id', 'id');
    }

    public function detailItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            InternalBorrowDetailItem::class,
            InternalBorrowDetail::class,
            'internal_borrow_slip_id',
            'internal_borrow_detail_id',
            'id',
            'id'
        );
    }
}
