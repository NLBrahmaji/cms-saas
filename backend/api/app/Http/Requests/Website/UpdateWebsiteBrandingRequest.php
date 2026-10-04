<?php

namespace App\Http\Requests\Website;

use App\Models\Website;
use App\Support\Media\WebsiteSelectableMedia;
use App\Support\Website\WebsiteBrandingEffectiveState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWebsiteBrandingRequest extends FormRequest
{
    /**
     * @var array<string, int|null>
     */
    private array $brandingChanges = [];

    /**
     * @var array<string, string>
     */
    private const INVALID_MEDIA_MESSAGES = [
        'logo_media_id' => 'The selected logo media id is invalid.',
        'logo_light_media_id' => 'The selected logo light media id is invalid.',
        'logo_dark_media_id' => 'The selected logo dark media id is invalid.',
        'favicon_media_id' => 'The selected favicon media id is invalid.',
    ];

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
            'logo_media_id' => ['sometimes', 'nullable', 'integer'],
            'logo_light_media_id' => ['sometimes', 'nullable', 'integer'],
            'logo_dark_media_id' => ['sometimes', 'nullable', 'integer'],
            'favicon_media_id' => ['sometimes', 'nullable', 'integer'],
            'theme' => ['prohibited'],
            'website_id' => ['prohibited'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, int|null>
     */
    public function brandingChanges(): array
    {
        return $this->brandingChanges;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = WebsiteBrandingEffectiveState::FIELDS;
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($this->all() === []) {
                $validator->errors()->add('logo_media_id', 'At least one supported field must be provided.');
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

                if ($value === null) {
                    $changes[$field] = null;

                    continue;
                }

                if (! is_int($value)) {
                    $validator->errors()->add($field, 'The '.$field.' field must be an integer.');

                    continue;
                }

                if (! $selector->isSelectableRasterImage($website, $value)) {
                    $validator->errors()->add($field, self::INVALID_MEDIA_MESSAGES[$field]);

                    continue;
                }

                $changes[$field] = $value;
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($changes === []) {
                $validator->errors()->add('logo_media_id', 'At least one supported field must be provided.');
            }

            $this->brandingChanges = $changes;
        });
    }
}
