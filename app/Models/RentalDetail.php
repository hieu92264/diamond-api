<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalDetail extends Model
{
    protected $fillable = [
        'is_active',
        'rental_slip_id',
        'equipment_prop_id',
        'rented_quantity',
        'returned_quantity',
        'lost_quantity',
        'damaged_quantity',
        'rental_unit_price',
        'rental_days',
        'line_rental_amount',
        'deposit_amount',
        'compensation_amount',
        'condition_on_rent',
        'condition_on_return',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rented_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'lost_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'rental_days' => 'integer',
            'rental_unit_price' => 'decimal:2',
            'line_rental_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'compensation_amount' => 'decimal:2',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePendingReturn(Builder $query): Builder
    {
        return $query->whereRaw('(returned_quantity + lost_quantity + damaged_quantity) < rented_quantity');
    }

    public function scopeHasIncident(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('lost_quantity', '>', 0)
                ->orWhere('damaged_quantity', '>', 0);
        });
    }

    public function rentalSlip(): BelongsTo
    {
        return $this->belongsTo(RentalSlip::class, 'rental_slip_id', 'id');
    }

    public function equipmentProp(): BelongsTo
    {
        return $this->belongsTo(EquipmentProp::class, 'equipment_prop_id', 'id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(RentalIncident::class, 'rental_detail_id', 'id');
    }

    public function detailItems(): HasMany
    {
        return $this->hasMany(RentalDetailItem::class, 'rental_detail_id', 'id');
    }
}
