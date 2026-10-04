<?php

namespace App\Http\Requests\Website;

use App\Models\Website;
use App\Support\Media\WebsiteSelectableMedia;
use App\Support\Website\WebsiteSeoEffectiveState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorInstance;

class UpdateWebsiteSeoRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    private array $seoChanges = [];

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
            'id' => ['prohibited'],
            'website_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'canonical_url' => ['prohibited'],
            'meta_title' => ['prohibited'],
            'meta_description' => ['prohibited'],
            'default_og_title' => ['prohibited'],
            'default_og_description' => ['prohibited'],
        ];
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
            $allowed = WebsiteSeoEffectiveState::FIELDS;
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($this->all() === []) {
                $validator->errors()->add('title_suffix', 'At least one supported field must be provided.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $website = $this->route('website');

            if (! $website instanceof Website) {
                return;
            }

            $changes = [];
            $selector = app(WebsiteSelectableMedia::class);

            foreach ($allowed as $field) {
                if (! $this->has($field)) {
                    continue;
                }

                $value = $this->input($field);

                if (in_array($field, ['robots_index', 'robots_follow'], true)) {
                    if (! is_bool($value)) {
                        $validator->errors()->add($field, 'The '.$field.' field must be true or false.');

                        continue;
                    }

                    $changes[$field] = $value;

                    continue;
                }

                if ($field === 'default_og_image_id') {
                    if ($value === null) {
                        $changes[$field] = null;

                        continue;
                    }

                    if (! is_int($value)) {
                        $validator->errors()->add($field, 'The default og image id field must be an integer.');

                        continue;
                    }

                    if (! $selector->isSelectableRasterImage($website, $value)) {
                        $validator->errors()->add($field, 'The selected default og image id is invalid.');

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
                $validator->errors()->add('title_suffix', 'At least one supported field must be provided.');

                return;
            }

            $fieldValidator = Validator::make($changes, [
                'title_suffix' => ['nullable', 'string', 'max:255'],
                'default_description' => ['nullable', 'string'],
            ]);

            if ($fieldValidator->fails()) {
                $validator->errors()->merge($fieldValidator->errors());

                return;
            }

            $this->seoChanges = $changes;
        });
    }
}
