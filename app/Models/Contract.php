<?php

namespace App\Models;

use App\Enums\Contract as ContractType;
use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'signed_date',
        'salary_agreement',
        'file_path',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => ContractType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'signed_date' => 'date',
            'salary_agreement' => 'decimal:2',
            'status' => ContractStatus::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus(Builder $query, ContractStatus|string $status): Builder
    {
        return $query->where(
            'status',
            $status instanceof ContractStatus ? $status->value : $status
        );
    }

    public function scopeType(Builder $query, ContractType|string $type): Builder
    {
        return $query->where(
            'type',
            $type instanceof ContractType ? $type->value : $type
        );
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->whereDate('start_date', '<=', today())
            ->where(function (Builder $query): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', today());
            });
    }
}
