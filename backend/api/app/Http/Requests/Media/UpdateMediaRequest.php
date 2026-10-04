<?php

namespace App\Http\Requests\Media;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMediaRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    private array $metadataChanges = [];

    public function authorize(): bool
    {
        $media = $this->route('media');

        return $media instanceof Media && ($this->user()?->can('update', $media) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'website_id' => ['prohibited'],
            'disk' => ['prohibited'],
            'path' => ['prohibited'],
            'original_name' => ['prohibited'],
            'mime_type' => ['prohibited'],
            'extension' => ['prohibited'],
            'size' => ['prohibited'],
            'width' => ['prohibited'],
            'height' => ['prohibited'],
            'source' => ['prohibited'],
            'created_by' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metadataChanges(): array
    {
        return $this->metadataChanges;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['alt_text', 'title'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($this->all() === []) {
                $validator->errors()->add('alt_text', 'At least one supported field must be provided.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $changes = [];

            foreach ($allowed as $field) {
                if ($this->exists($field)) {
                    $changes[$field] = $this->input($field);
                }
            }

            $this->metadataChanges = $changes;
        });
    }
}
