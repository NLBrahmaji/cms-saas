<?php

namespace App\Http\Requests\Page;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use stdClass;

class UpdatePageSectionRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    private array $settingsDocument = [];

    private bool $includesSettings = false;

    private bool $includesIsVisible = false;

    private bool $isVisible = false;

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
     * @return array{settings?: array<string, mixed>, is_visible?: bool}
     */
    public function sectionChanges(): array
    {
        $changes = [];

        if ($this->includesSettings) {
            $changes['settings'] = $this->settingsDocument;
        }

        if ($this->includesIsVisible) {
            $changes['is_visible'] = $this->isVisible;
        }

        return $changes;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $payload = json_decode($this->getContent());

            if (! $payload instanceof stdClass) {
                $validator->errors()->add('settings', 'At least one supported field must be provided.');

                return;
            }

            $properties = get_object_vars($payload);
            $allowed = ['settings', 'is_visible'];
            $unknown = array_diff(array_keys($properties), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($properties === []) {
                $validator->errors()->add('settings', 'At least one supported field must be provided.');

                return;
            }

            if (array_key_exists('settings', $properties)) {
                $settings = $properties['settings'];

                if ($settings === null) {
                    $validator->errors()->add('settings', 'The settings field must be a JSON object.');
                } elseif (is_array($settings)) {
                    $validator->errors()->add('settings', 'The settings field must be a JSON object.');
                } elseif (! $settings instanceof stdClass) {
                    $validator->errors()->add('settings', 'The settings field must be a JSON object.');
                } else {
                    $this->includesSettings = true;
                    $this->settingsDocument = json_decode(json_encode($settings, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                }
            }

            if (array_key_exists('is_visible', $properties)) {
                $visibility = $properties['is_visible'];

                if (! is_bool($visibility)) {
                    $validator->errors()->add('is_visible', 'The is visible field must be true or false.');
                } else {
                    $this->includesIsVisible = true;
                    $this->isVisible = $visibility;
                }
            }

            if (! $this->includesSettings && ! $this->includesIsVisible && $validator->errors()->isEmpty()) {
                $validator->errors()->add('settings', 'At least one supported field must be provided.');
            }
        });
    }
}
