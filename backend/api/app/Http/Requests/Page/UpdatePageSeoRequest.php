<?php

namespace App\Http\Requests\Page;

use App\Rules\HttpHttpsAbsoluteUrl;
use App\Support\Page\PageSeoEffectiveState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;
use stdClass;

class UpdatePageSeoRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    private array $seoChanges = [];

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
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function seoChanges(): array
    {
        return $this->seoChanges;
    }

    public function withValidator(ValidatorInstance $validator): void
    {
        $validator->after(function (ValidatorInstance $validator): void {
            $payload = json_decode($this->getContent());

            if (! $payload instanceof stdClass) {
                $validator->errors()->add('meta_title', 'At least one supported field must be provided.');

                return;
            }

            $properties = get_object_vars($payload);
            $allowed = PageSeoEffectiveState::WRITABLE_FIELDS;
            $unknown = array_diff(array_keys($properties), $allowed);

            foreach ($unknown as $field) {
                $message = $field === 'og_image_id'
                    ? 'This field is not supported yet.'
                    : 'This field is not allowed.';

                $validator->errors()->add($field, $message);
            }

            if ($properties === []) {
                $validator->errors()->add('meta_title', 'At least one supported field must be provided.');

                return;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $changes = [];

            foreach ($allowed as $field) {
                if (! array_key_exists($field, $properties)) {
                    continue;
                }

                $value = $properties[$field];

                if (in_array($field, ['robots_index', 'robots_follow'], true)) {
                    if ($value !== null && ! is_bool($value)) {
                        $validator->errors()->add($field, 'The '.$field.' field must be true or false.');

                        continue;
                    }

                    $changes[$field] = $value;

                    continue;
                }

                if ($value !== null && ! is_string($value)) {
                    $validator->errors()->add($field, 'The '.$field.' field must be a string.');

                    continue;
                }

                $changes[$field] = $value;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($changes === []) {
                $validator->errors()->add('meta_title', 'At least one supported field must be provided.');

                return;
            }

            $fieldValidator = Validator::make($changes, [
                'meta_title' => ['nullable', 'string', 'max:255'],
                'meta_description' => ['nullable', 'string'],
                'og_title' => ['nullable', 'string', 'max:255'],
                'og_description' => ['nullable', 'string'],
                'canonical_url' => ['nullable', 'string', 'max:2048', new HttpHttpsAbsoluteUrl],
                'robots_index' => ['nullable', 'boolean'],
                'robots_follow' => ['nullable', 'boolean'],
            ]);

            if ($fieldValidator->fails()) {
                $validator->errors()->merge($fieldValidator->errors());

                return;
            }

            $this->seoChanges = $changes;
        });
    }
}
