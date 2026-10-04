<?php

namespace App\Http\Requests\Page;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublishPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page !== null && ($this->user()?->can('publish', $page) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['prohibited'],
            'slug' => ['prohibited'],
            'is_home' => ['prohibited'],
            'parent_page_id' => ['prohibited'],
            'version' => ['prohibited'],
            'draft_version_id' => ['prohibited'],
            'published_version_id' => ['prohibited'],
            'published_at' => ['prohibited'],
            'published_by' => ['prohibited'],
            'created_by' => ['prohibited'],
            'website_id' => ['prohibited'],
            'page_id' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unknown = array_keys($this->all());

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }
        });
    }
}
