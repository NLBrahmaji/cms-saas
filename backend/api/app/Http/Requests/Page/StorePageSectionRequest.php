<?php

namespace App\Http\Requests\Page;

use App\Support\Section\ActiveSectionTemplateResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePageSectionRequest extends FormRequest
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
            'section_template_id' => ['required', 'integer'],
            'public_id' => ['prohibited'],
            'id' => ['prohibited'],
            'page_version_id' => ['prohibited'],
            'sort_order' => ['prohibited'],
            'is_visible' => ['prohibited'],
            'settings' => ['prohibited'],
            'content' => ['prohibited'],
            'section_type_id' => ['prohibited'],
            'account_id' => ['prohibited'],
            'website_id' => ['prohibited'],
            'page_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['section_template_id'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->has('section_template_id')) {
                return;
            }

            $resolver = app(ActiveSectionTemplateResolver::class);
            $template = $resolver->findActive($this->integer('section_template_id'));

            if ($template === null) {
                $validator->errors()->add('section_template_id', 'The selected section template is invalid.');
            }
        });
    }
}
