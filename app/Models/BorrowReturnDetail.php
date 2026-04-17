<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BorrowReturnDetail extends Model
{
    protected $fillable = [
        'is_active',
        'borrow_return_slip_id',
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
        return $query->whereColumn('returned_quantity', '<', 'borrowed_quantity');
    }

    public function scopeHasIncident(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('lost_quantity', '>', 0)
                ->orWhere('damaged_quantity', '>', 0);
        });
    }
}
