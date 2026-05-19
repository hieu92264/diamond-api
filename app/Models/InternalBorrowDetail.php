<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalBorrowDetail extends Model
{
    protected $table = 'internal_borrow_details';

    protected $fillable = [
        'is_active',
        'internal_borrow_slip_id',
        'equipment_prop_id',
        'borrowed_quantity',
        'returned_quantity',
        'lost_quantity',
        'damaged_quantity',
        'condition_on_borrow',
        'condition_on_return',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'borrowed_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'lost_quantity' => 'integer',
            'damaged_quantity' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePendingReturn(Builder $query): Builder
    {
        return $query->whereRaw('(returned_quantity + lost_quantity + damaged_quantity) < borrowed_quantity');
    }

    public function scopeHasIncident(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('lost_quantity', '>', 0)
                ->orWhere('damaged_quantity', '>', 0);
        });
    }

    public function borrowSlip(): BelongsTo
    {
        return $this->belongsTo(InternalBorrowSlip::class, 'internal_borrow_slip_id', 'id');
    }

    public function equipmentProp(): BelongsTo
    {
        return $this->belongsTo(EquipmentProp::class, 'equipment_prop_id', 'id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(InternalIncident::class, 'internal_borrow_detail_id', 'id');
    }

    public function detailItems(): HasMany
    {
        return $this->hasMany(InternalBorrowDetailItem::class, 'internal_borrow_detail_id', 'id');
    }
}
