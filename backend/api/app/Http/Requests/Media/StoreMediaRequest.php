<?php

namespace App\Http\Requests\Media;

use App\Support\Media\MediaRasterImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('media.upload') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'image',
                'mimetypes:'.implode(',', MediaRasterImage::ALLOWED_MIME_TYPES),
                'max:'.MediaRasterImage::MAX_SIZE_KIBIBYTES,
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['file'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $file = $this->file('file');

            if ($file === null) {
                return;
            }

            $originalName = basename($file->getClientOriginalName());

            if (strlen($originalName) > 255) {
                $validator->errors()->add('file', 'The original filename is too long.');
            }

            $imageInfo = @getimagesize($file->getRealPath());

            if ($imageInfo === false) {
                $validator->errors()->add('file', 'The file must be a valid image.');
            }
        });
    }
}
