<?php

namespace App\Models;

use App\Enums\Department;
use App\Enums\Gender;
use App\Enums\Position;
use App\Enums\Work;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        $value = $department instanceof Department ? $department->value : strtoupper($department);

        return $query->where('department', $value);
    }

    public function scopePosition(Builder $query, Position|string $position): Builder
    {
        $value = $position instanceof Position ? $position->value : strtoupper($position);

        return $query->where('position', $value);
    }

    public function scopeWorkStatus(Builder $query, Work|string $status): Builder
    {
        $value = $status instanceof Work ? $status->value : strtoupper($status);

        return $query->where('work_status', $value);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id', 'id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'employee_id', 'id');
    }

    public function internalBorrowSlips(): HasMany
    {
        return $this->hasMany(InternalBorrowSlip::class, 'employee_id', 'id');
    }

    public function internal_borrow_slips(): HasMany
    {
        return $this->internalBorrowSlips();
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'manager_employee_id', 'id');
    }
}
