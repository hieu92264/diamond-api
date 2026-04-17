<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'borrow_return_detail_id',
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
            'compensation_amount' => 'decimal:2',
            'resolved_at' => 'datetime',
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

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }
}
