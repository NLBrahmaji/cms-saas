<?php

namespace App\Http\Requests\Page;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('page.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'website_id' => ['prohibited'],
            'page_id' => ['prohibited'],
            'id' => ['prohibited'],
            'slug' => ['prohibited'],
            'is_home' => ['prohibited'],
            'parent_page_id' => ['prohibited'],
            'version' => ['prohibited'],
            'draft_version_id' => ['prohibited'],
            'published_version_id' => ['prohibited'],
            'published_at' => ['prohibited'],
            'created_by' => ['prohibited'],
            'published_by' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['name'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }
        });
    }
}
