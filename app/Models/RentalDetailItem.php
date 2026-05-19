<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalDetailItem extends Model
{
    protected $fillable = [
        'is_active',
        'rental_detail_id',
        'inventory_item_id',
        'condition_on_rent_id',
        'condition_on_return_id',
        'rented_at',
        'returned_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rented_at' => 'datetime',
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

    public function rentalDetail(): BelongsTo
    {
        return $this->belongsTo(RentalDetail::class, 'rental_detail_id', 'id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'id');
    }

    public function conditionOnRent(): BelongsTo
    {
        return $this->belongsTo(InventoryCondition::class, 'condition_on_rent_id', 'id');
    }

    public function conditionOnReturn(): BelongsTo
    {
        return $this->belongsTo(InventoryCondition::class, 'condition_on_return_id', 'id');
    }
}
