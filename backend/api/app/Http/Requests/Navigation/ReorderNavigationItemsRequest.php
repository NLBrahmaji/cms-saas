<?php

namespace App\Http\Requests\Navigation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderNavigationItemsRequest extends FormRequest
{
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
            'parent_id' => ['present', 'nullable', 'uuid'],
            'item_ids' => ['present', 'array'],
            'item_ids.*' => ['required', 'string', 'uuid'],
        ];
    }

    public function parentPublicId(): ?string
    {
        $parentId = $this->input('parent_id');

        return is_string($parentId) ? $parentId : null;
    }

    /**
     * @return list<string>
     */
    public function itemIds(): array
    {
        return array_values($this->input('item_ids', []));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['parent_id', 'item_ids'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $itemIds = $this->input('item_ids', []);

            if (! is_array($itemIds)) {
                return;
            }

            if (count($itemIds) !== count(array_unique($itemIds))) {
                $validator->errors()->add('item_ids', 'The item ids must not contain duplicates.');
            }
        });
    }
}
