<?php

namespace App\Http\Requests\Navigation;

use App\Models\Navigation;
use App\Models\Website;
use App\Support\Navigation\NavigationKeySyntax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNavigationRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    private array $metadataChanges = [];

    public function authorize(): bool
    {
        $navigation = $this->route('navigation');

        return $navigation !== null && ($this->user()?->can('update', $navigation) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $website = $this->route('website');
        $navigation = $this->route('navigation');

        $websiteId = $website instanceof Website ? $website->id : null;
        $navigationId = $navigation instanceof Navigation ? $navigation->id : null;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'key' => [
                'sometimes',
                'required',
                'string',
                'max:'.NavigationKeySyntax::MAX_LENGTH,
                'regex:'.NavigationKeySyntax::PATTERN,
                Rule::unique('navigations', 'key')
                    ->where('website_id', $websiteId)
                    ->ignore($navigationId),
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

    /**
     * @return array<string, string>
     */
    public function metadataChanges(): array
    {
        return $this->metadataChanges;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['name', 'key'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($this->all() === []) {
                $validator->errors()->add('name', 'At least one supported field must be provided.');
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
