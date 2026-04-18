<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id'),
                Rule::unique('users', 'employee_id'),
            ],
            'is_active' => ['nullable', 'boolean'],
            'last_login_at' => ['nullable', 'date'],
        ];
    }
}
