<?php

namespace App\Http\Requests\ItemCategory;

use App\Enums\ItemCategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('item_categories', 'name')->ignore($this->route('id')),
            ],
            'type' => ['sometimes', 'required', Rule::enum(ItemCategoryType::class)],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
