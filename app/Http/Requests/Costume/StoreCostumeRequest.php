<?php

namespace App\Http\Requests\Costume;

use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $imageIds = $this->input('image_ids', $this->input('images_ids'));

        if ($imageIds !== null && ! is_array($imageIds)) {
            $imageIds = [$imageIds];
        }

        $this->merge([
            'image_ids' => $imageIds,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')],
            'color' => ['nullable', 'string', 'max:50'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['string', 'max:50'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'image_ids' => ['nullable', 'array'],
            'image_ids.*' => ['integer', Rule::exists('gallery_images', 'id')],
            'rental_price_per_day' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
