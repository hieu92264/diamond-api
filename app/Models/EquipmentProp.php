<?php

namespace App\Models;

use App\Enums\EquipmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EquipmentProp extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'name',
        'item_category_id',
        'unit',
        'warehouse_id',
        'status',
        'quantity_total',
        'quantity_available',
        'minimum_quantity',
        'purchase_date',
        'item_value',
        'default_rental_price',
        'default_deposit_price',
        'remarks',
        'image_path',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status' => EquipmentStatus::class,
            'quantity_total' => 'integer',
            'quantity_available' => 'integer',
            'minimum_quantity' => 'integer',
            'purchase_date' => 'date',
            'item_value' => 'decimal:2',
            'default_rental_price' => 'decimal:2',
            'default_deposit_price' => 'decimal:2',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus(Builder $query, EquipmentStatus|string $status): Builder
    {
        $value = $status instanceof EquipmentStatus ? $status->value : strtoupper($status);

        return $query->where('status', $value);
    }

    public function scopeInWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('quantity_available', '>', 0)
            ->where('status', EquipmentStatus::AVAILABLE->value);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity_available', '<=', 'minimum_quantity');
    }
}