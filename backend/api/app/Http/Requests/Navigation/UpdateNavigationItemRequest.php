<?php

namespace App\Http\Requests\Navigation;

use App\Navigation\NavigationItemType;
use App\Rules\NavigationItemUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateNavigationItemRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    private array $itemChanges = [];

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
        return [
            'type' => ['sometimes', new Enum(NavigationItemType::class)],
            'page_id' => ['sometimes', 'nullable', 'integer'],
            'url' => ['sometimes', 'nullable', 'string', 'max:2048', new NavigationItemUrl],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'uuid'],
            'open_in_new_tab' => ['sometimes'],
            'id' => ['prohibited'],
            'public_id' => ['prohibited'],
            'navigation_version_id' => ['prohibited'],
            'sort_order' => ['prohibited'],
            'navigation_id' => ['prohibited'],
            'website_id' => ['prohibited'],
            'account_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemChanges(): array
    {
        return $this->itemChanges;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['type', 'page_id', 'url', 'label', 'parent_id', 'open_in_new_tab'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($this->all() === []) {
                $validator->errors()->add('type', 'At least one supported field must be provided.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->has('open_in_new_tab')) {
                if ($this->input('open_in_new_tab') === null || ! is_bool($this->input('open_in_new_tab'))) {
                    $validator->errors()->add('open_in_new_tab', 'The open in new tab field must be true or false.');
                }
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

            $this->itemChanges = $changes;
        });
    }
}
