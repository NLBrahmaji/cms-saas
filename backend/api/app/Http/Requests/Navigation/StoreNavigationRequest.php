<?php

namespace App\Http\Requests\Navigation;

use App\Models\Website;
use App\Support\Navigation\NavigationKeySyntax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNavigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('navigation.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $website = $this->route('website');

        $websiteId = $website instanceof Website ? $website->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required',
                'string',
                'max:'.NavigationKeySyntax::MAX_LENGTH,
                'regex:'.NavigationKeySyntax::PATTERN,
                Rule::unique('navigations', 'key')->where('website_id', $websiteId),
            ],
            'website_id' => ['prohibited'],
            'navigation_id' => ['prohibited'],
            'id' => ['prohibited'],
            'draft_version_id' => ['prohibited'],
            'published_version_id' => ['prohibited'],
            'version' => ['prohibited'],
            'created_by' => ['prohibited'],
            'published_by' => ['prohibited'],
            'published_at' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'items' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['name', 'key'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }
        });
    }
}
