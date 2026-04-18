<?php

namespace App\Models;

use App\Enums\InventoryReferenceType;
use App\Enums\InventoryTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'equipment_prop_id',
        'warehouse_id',
        'transaction_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reference_type',
        'reference_id',
        'note',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => InventoryTransactionType::class,
            'quantity' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
            'reference_type' => InventoryReferenceType::class,
            'reference_id' => 'integer',
        ];
    }

    public function scopeTransactionType(Builder $query, InventoryTransactionType|string $type): Builder
    {
        $value = $type instanceof InventoryTransactionType ? $type->value : strtoupper($type);

        return $query->where('transaction_type', $value);
    }

    public function scopeReferenceType(Builder $query, InventoryReferenceType|string $type): Builder
    {
        $value = $type instanceof InventoryReferenceType ? $type->value : strtoupper($type);

        return $query->where('reference_type', $value);
    }

    public function scopeForReference(Builder $query, InventoryReferenceType|string $type, int|string $referenceId): Builder
    {
        $value = $type instanceof InventoryReferenceType ? $type->value : strtoupper($type);

        return $query
            ->where('reference_type', $value)
            ->where('reference_id', $referenceId);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(EquipmentProp::class, 'equipment_prop_id', 'id');
    }

    public function equipmentProp(): BelongsTo
    {
        return $this->belongsTo(EquipmentProp::class, 'equipment_prop_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by', 'id');
    }
}
