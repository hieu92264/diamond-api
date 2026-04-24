<?php

namespace App\Http\Requests\EquipmentProp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentPropRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'dimensions' => $this->input('dimensions', $this->input('demensions')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')],
            'rental_price_per_day' => ['required', 'numeric', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.width_cm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.height_cm' => ['nullable', 'numeric', 'min:0'],
            'dimensions.depth_cm' => ['nullable', 'numeric', 'min:0'],
            'is_fragile' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
