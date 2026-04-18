<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategory extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'name',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function equipments():HasMany
    {
        return $this->hasMany(EquipmentProp::class, 'item_category_id', 'id');
    }
}
