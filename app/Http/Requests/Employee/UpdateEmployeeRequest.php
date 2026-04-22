<?php

namespace App\Http\Requests\Employee;

use App\Enums\Department;
use App\Enums\Gender;
use App\Enums\Position;
use App\Enums\Work;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employee = $this->resolveEmployee();

        return [
            'is_active' => ['sometimes', 'boolean'],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
//            'department' => ['sometimes', 'required', Rule::enum(Department::class)],
            'position' => ['sometimes', 'required', Rule::enum(Position::class)],
            'hire_date' => ['nullable', 'date'],
            'work_status' => ['sometimes', 'required', Rule::enum(Work::class)],
            'remarks' => ['nullable', 'string'],
        ];
    }

    protected function resolveEmployee(): Employee|int|string|null
    {
        return $this->route('employee')
            ?? $this->route('Employee')
            ?? $this->route('id');
    }
}
