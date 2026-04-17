<?php

namespace App\Models;

use App\Enums\CustomerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'type',
        'full_name',
        'company_name',
        'contact_person',
        'phone',
        'email',
        'tax_code',
        'address',
        'identity_no',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => CustomerType::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeType(Builder $query, CustomerType|string $type): Builder
    {
        $value = $type instanceof CustomerType ? $type->value : strtoupper($type);

        return $query->where('type', $value);
    }

    public function scopeSearch(Builder $query, string $keyword): Builder
    {
        $keyword = trim($keyword);

        return $query->where(function (Builder $query) use ($keyword): void {
            $query->where('code', 'like', "%{$keyword}%")
                ->orWhere('full_name', 'like', "%{$keyword}%")
                ->orWhere('company_name', 'like', "%{$keyword}%")
                ->orWhere('phone', 'like', "%{$keyword}%");
        });
    }
}