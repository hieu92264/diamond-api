<?php

namespace App\Http\Requests\ImageGallery;

use App\Enums\GalleryItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('file')) {
            $files = $this->file('file');
            $this->files->set('file', is_array($files) ? $files : [$files]);
        }
    }

    public function rules(): array
    {
        return [
            'item_type' => ['required', Rule::enum(GalleryItemType::class)],
            'file' => ['required', 'array', 'min:1'],
            'file.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
