<?php

namespace App\Http\Requests\Website;

use App\Http\Resources\Website\WebsiteSettingResource;
use App\Models\WebsiteSetting;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWebsiteSettingsRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ADDRESS_KEYS = WebsiteSettingResource::ADDRESS_KEYS;

    /**
     * @var list<string>
     */
    private const SOCIAL_LINK_KEYS = WebsiteSettingResource::SOCIAL_LINK_KEYS;

    /**
     * @var list<string>
     */
    private const EDITABLE_TOP_LEVEL_KEYS = [
        'site_name',
        'tagline',
        'contact_email',
        'contact_phone',
        'address',
        'social_links',
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
        $rules = [
            'site_name' => ['sometimes', 'required', 'string', 'max:255'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'array', $this->structuredObjectRule('address')],
            'social_links' => ['sometimes', 'nullable', 'array', $this->structuredObjectRule('social_links')],
            'id' => ['prohibited'],
            'website_id' => ['prohibited'],
            'account_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];

        foreach (self::ADDRESS_KEYS as $key) {
            $rules['address.'.$key] = ['sometimes', 'nullable', 'string', 'max:255'];
        }

        foreach (self::SOCIAL_LINK_KEYS as $key) {
            $rules['social_links.'.$key] = [
                'sometimes',
                'nullable',
                'string',
                $this->httpOrHttpsUrlRule('social_links.'.$key),
            ];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unknown = array_diff(array_keys($this->all()), self::EDITABLE_TOP_LEVEL_KEYS);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($this->has('address') && is_array($this->input('address')) && ! array_is_list($this->input('address'))) {
                $this->rejectUnknownNestedKeys($validator, 'address', self::ADDRESS_KEYS);
            }

            if ($this->has('social_links') && is_array($this->input('social_links')) && ! array_is_list($this->input('social_links'))) {
                $this->rejectUnknownNestedKeys($validator, 'social_links', self::SOCIAL_LINK_KEYS);
            }
        });
    }

    public function applyTo(WebsiteSetting $settings): void
    {
        if ($this->has('site_name')) {
            $settings->site_name = $this->string('site_name')->toString();
        }

        if ($this->has('tagline')) {
            $settings->tagline = $this->input('tagline');
        }

        if ($this->has('contact_email')) {
            $settings->contact_email = $this->input('contact_email');
        }

        if ($this->has('contact_phone')) {
            $settings->contact_phone = $this->input('contact_phone');
        }

        if ($this->has('address')) {
            $settings->address = $this->mergeStructuredField(
                $settings->address,
                $this->input('address'),
                self::ADDRESS_KEYS,
            );
        }

        if ($this->has('social_links')) {
            $settings->social_links = $this->mergeStructuredField(
                $settings->social_links,
                $this->input('social_links'),
                self::SOCIAL_LINK_KEYS,
            );
        }
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    private function mergeStructuredField(?array $existing, mixed $incoming, array $allowedKeys): ?array
    {
        if ($incoming === null) {
            return null;
        }

        if (! is_array($incoming)) {
            return $existing;
        }

        $merged = $existing ?? [];

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $incoming)) {
                $merged[$key] = $incoming[$key];
            }
        }

        return $merged === [] ? [] : $merged;
    }

    /**
     * @param  list<string>  $allowedKeys
     */
    private function rejectUnknownNestedKeys(Validator $validator, string $field, array $allowedKeys): void
    {
        $value = $this->input($field);

        if (! is_array($value)) {
            return;
        }

        foreach (array_keys($value) as $key) {
            if (! in_array($key, $allowedKeys, true)) {
                $validator->errors()->add($field.'.'.$key, 'This field is not allowed.');
            }
        }
    }

    private function structuredObjectRule(string $field): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($field): void {
            if ($value === null) {
                return;
            }

            if (! is_array($value) || array_is_list($value)) {
                $fail('The '.$field.' must be an object.');
            }
        };
    }

    private function httpOrHttpsUrlRule(string $attribute): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null) {
                return;
            }

            if (! is_string($value)) {
                $fail('The '.$attribute.' must be a valid URL.');

                return;
            }

            if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                $fail('The '.$attribute.' must be a valid URL.');

                return;
            }

            $scheme = parse_url($value, PHP_URL_SCHEME);

            if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                $fail('The '.$attribute.' must use HTTP or HTTPS.');
            }
        };
    }
}
