<?php

namespace App\Http\Requests\Website;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWebsiteHomepageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $website = $this->route('website');

        return $website !== null && ($this->user()?->can('update', $website) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page_id' => ['present', 'nullable', 'integer'],
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
            'status' => ['prohibited'],
            'timezone' => ['prohibited'],
            'subdomain' => ['prohibited'],
            'account_id' => ['prohibited'],
            'website_id' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['page_id'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }
        });
    }
}
