<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalIncident extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'rental_detail_id',
        'inventory_item_id',
        'incident_description',
        'incident_type',
        'status',
        'compensation_amount',
        'resolved_by_id',
        'resolved_at',
        'resolution',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'incident_type' => IncidentType::class,
            'status' => IncidentStatus::class,
            'compensation_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus(Builder $query, IncidentStatus|string $status): Builder
    {
        $value = $status instanceof IncidentStatus ? $status->value : strtoupper($status);

        return $query->where('status', $value);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', IncidentStatus::OPEN->value);
    }

    public function scopeType(Builder $query, IncidentType|string $type): Builder
    {
        $value = $type instanceof IncidentType ? $type->value : strtoupper($type);

        return $query->where('incident_type', $value);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    public function rentalDetail(): BelongsTo
    {
        return $this->belongsTo(RentalDetail::class, 'rental_detail_id', 'id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id', 'id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id', 'id');
    }
}
