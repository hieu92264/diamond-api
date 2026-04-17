<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaintenanceTicket extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'item_id',
        'maintenance_type',
        'reported_date',
        'started_date',
        'expected_return_date',
        'return_date',
        'status',
        'vendor',
        'cost',
        'remarks',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'reported_date' => 'date',
            'started_date' => 'date',
            'expected_return_date' => 'date',
            'return_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'OPEN');
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereNotNull('started_date')->whereNull('return_date');
    }
}
