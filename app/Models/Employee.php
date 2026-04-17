<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\Gender;
use App\Enums\Position;
use App\Enums\Work;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'is_active',
        'employee_code',
        'full_name',
        'dob',
        'gender',
        'phone',
        'address',
        'department',
        'position',
        'hire_date',
        'work_status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'dob' => 'date',
            'gender' => Gender::class,
            'department' => Department::class,
            'position' => Position::class,
            'hire_date' => 'date',
            'work_status' => Work::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDepartment(Builder $query, Department|string $department): Builder
    {
        return $query->where(
            'department',
            $department instanceof Department ? $department->value : $department
        );
    }

    public function scopePosition(Builder $query, Position|string $position): Builder
    {
        return $query->where(
            'position',
            $position instanceof Position ? $position->value : $position
        );
    }

    public function scopeWorkStatus(Builder $query, Work|string $status): Builder
    {
        return $query->where(
            'work_status',
            $status instanceof Work ? $status->value : $status
        );
    }
}
