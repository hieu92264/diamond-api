<?php

namespace App\Models;

use App\Enums\InventoryItemStatus;
use App\Enums\ItemCategoryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $table = 'inventory';

    protected $fillable = [
        'is_active',
        'sku',
        'item_id',
        'item_type',
        'inventory_condition_id',
        'warehouse_id',
        'status',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'item_type' => ItemCategoryType::class,
            'status' => InventoryItemStatus::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', InventoryItemStatus::AVAILABLE->value);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EquipmentProp::class, 'item_id', 'id');
    }

    public function inventoryCondition(): BelongsTo
    {
        return $this->belongsTo(InventoryCondition::class, 'inventory_condition_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function internalBorrowDetailItems(): HasMany
    {
        return $this->hasMany(InternalBorrowDetailItem::class, 'inventory_item_id', 'id');
    }

    public function rentalDetailItems(): HasMany
    {
        return $this->hasMany(RentalDetailItem::class, 'inventory_item_id', 'id');
    }

    public function internalIncidents(): HasMany
    {
        return $this->hasMany(InternalIncident::class, 'inventory_item_id', 'id');
    }

    public function rentalIncidents(): HasMany
    {
        return $this->hasMany(RentalIncident::class, 'inventory_item_id', 'id');
    }

    public function maintenanceTickets(): HasMany
    {
        return $this->hasMany(MaintenanceTicket::class, 'inventory_item_id', 'id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'inventory_item_id', 'id');
    }
}
