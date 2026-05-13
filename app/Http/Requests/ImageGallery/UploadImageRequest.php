<?php

namespace App\Http\Requests\ImageGallery;

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
        $data = $this->input('data');

        if (is_string($data) && $data !== '') {
            $decoded = json_decode($data, true);

            if (is_array($decoded)) {
                $this->merge($decoded);
            }
        }

        $files = $this->file('files', $this->file('file'));

        if ($files !== null) {
            $this->files->set('files', is_array($files) ? $files : [$files]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('item_categories', 'id')],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
