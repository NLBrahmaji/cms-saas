<?php

namespace App\Http\Requests\Page;

use App\Support\Page\PageSlugNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $page = $this->route('page');

        return $page !== null && ($this->user()?->can('update', $page) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:'.PageSlugNormalizer::MAX_LENGTH],
            'website_id' => ['prohibited'],
            'page_id' => ['prohibited'],
            'id' => ['prohibited'],
            'version' => ['prohibited'],
            'is_home' => ['prohibited'],
            'parent_page_id' => ['prohibited'],
            'draft_version_id' => ['prohibited'],
            'published_version_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'published_by' => ['prohibited'],
            'published_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('slug')) {
            return;
        }

        $normalizer = app(PageSlugNormalizer::class);
        $normalized = $normalizer->normalize($this->string('slug')->toString());

        if ($normalized !== null) {
            $this->merge(['slug' => $normalized]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['name', 'slug'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($this->has('slug') && ! $validator->errors()->has('slug')) {
                $normalizer = app(PageSlugNormalizer::class);
                $normalized = $normalizer->normalize($this->string('slug')->toString());

                if ($normalized === null) {
                    $validator->errors()->add('slug', 'The slug field is invalid.');
                }
            }
        });
    }
}
