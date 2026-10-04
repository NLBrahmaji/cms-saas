<?php

namespace App\Http\Requests\Navigation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublishNavigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $navigation = $this->route('navigation');

        return $navigation !== null && ($this->user()?->can('publish', $navigation) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['prohibited'],
            'key' => ['prohibited'],
            'draft_version_id' => ['prohibited'],
            'published_version_id' => ['prohibited'],
            'website_id' => ['prohibited'],
            'navigation_id' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }
        });
    }
}
