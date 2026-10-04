<?php

namespace App\Http\Requests\Page;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use stdClass;

class UpdatePageSectionContentRequest extends FormRequest
{
    /**
     * @var array<string, mixed>
     */
    private array $contentDocument = [];

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
    public function contentDocument(): array
    {
        return $this->contentDocument;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $payload = json_decode($this->getContent());

            if (! $payload instanceof stdClass) {
                $validator->errors()->add('content', 'The content field is required.');

                return;
            }

            $properties = get_object_vars($payload);
            $unknown = array_diff(array_keys($properties), ['content']);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if (! array_key_exists('content', $properties)) {
                $validator->errors()->add('content', 'The content field is required.');

                return;
            }

            $content = $properties['content'];

            if ($content === null) {
                $validator->errors()->add('content', 'The content field must be a JSON object.');

                return;
            }

            if (is_array($content)) {
                $validator->errors()->add('content', 'The content field must be a JSON object.');

                return;
            }

            if (! $content instanceof stdClass) {
                $validator->errors()->add('content', 'The content field must be a JSON object.');

                return;
            }

            $this->contentDocument = json_decode(json_encode($content, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        });
    }
}
