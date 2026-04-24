<?php

namespace App\Http\Requests\ImageGallery;

use App\Enums\GalleryItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_type' => ['sometimes', 'required', Rule::enum(GalleryItemType::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
