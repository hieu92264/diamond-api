<?php

namespace App\Http\Requests\Employee;

use App\Enums\Department;
use App\Enums\Gender;
use App\Enums\Position;
use App\Enums\Work;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'full_name' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
//            'department' => ['required', Rule::enum(Department::class)],
            'position' => ['required', Rule::enum(Position::class)],
            'hire_date' => ['nullable', 'date'],
            'work_status' => ['required', Rule::enum(Work::class)],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
