<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InternalIncident extends Model
{
    protected $table = 'internal_incidents';

    protected $fillable = [
        'is_active',
        'code',
        'internal_borrow_detail_id',
        'incident_description',
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

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }
}