<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
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
            'status' => MaintenanceStatus::class,
            'cost' => 'decimal:2',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeStatus(Builder $query, MaintenanceStatus|string $status): Builder
    {
        $value = $status instanceof MaintenanceStatus ? $status->value : strtoupper($status);

        return $query->where('status', $value);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', MaintenanceStatus::OPEN->value);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', MaintenanceStatus::IN_PROGRESS->value);
    }
}