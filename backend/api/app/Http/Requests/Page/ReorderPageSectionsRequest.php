<?php

namespace App\Http\Requests\Page;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderPageSectionsRequest extends FormRequest
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
            'section_ids' => ['present', 'array'],
            'section_ids.*' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * @return list<string>
     */
    public function sectionIds(): array
    {
        return array_values($this->input('section_ids', []));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['section_ids'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sectionIds = $this->input('section_ids', []);

            if (! is_array($sectionIds)) {
                return;
            }

            if (count($sectionIds) !== count(array_unique($sectionIds))) {
                $validator->errors()->add('section_ids', 'The section ids must not contain duplicates.');
            }
        });
    }
}
