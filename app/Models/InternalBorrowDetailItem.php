<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalBorrowDetailItem extends Model
{
    protected $fillable = [
        'is_active',
        'internal_borrow_detail_id',
        'inventory_item_id',
        'condition_on_borrow_id',
        'condition_on_return_id',
        'borrowed_at',
        'returned_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'borrowed_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereNull('returned_at');
    }

    public function borrowDetail(): BelongsTo
    {
        return $this->belongsTo(InternalBorrowDetail::class, 'internal_borrow_detail_id', 'id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'id');
    }

    public function conditionOnBorrow(): BelongsTo
    {
        return $this->belongsTo(InventoryCondition::class, 'condition_on_borrow_id', 'id');
    }

    public function conditionOnReturn(): BelongsTo
    {
        return $this->belongsTo(InventoryCondition::class, 'condition_on_return_id', 'id');
    }
}
