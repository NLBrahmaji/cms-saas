<?php

namespace App\Http\Requests\Navigation;

use App\Models\Page;
use App\Models\Website;
use App\Navigation\NavigationItemType;
use App\Rules\NavigationItemUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreNavigationItemRequest extends FormRequest
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
        $website = $this->route('website');
        $websiteId = $website instanceof Website ? $website->id : null;

        return [
            'type' => ['required', new Enum(NavigationItemType::class)],
            'page_id' => [
                'nullable',
                'integer',
                Rule::exists('pages', 'id')->where(function ($query) use ($websiteId): void {
                    $query->where('website_id', $websiteId)->whereNull('deleted_at');
                }),
            ],
            'url' => ['nullable', 'string', 'max:2048', new NavigationItemUrl],
            'label' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'uuid'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['type', 'page_id', 'url', 'label', 'parent_id', 'open_in_new_tab'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $field) {
                $validator->errors()->add($field, 'This field is not allowed.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->has('open_in_new_tab') && ! is_bool($this->input('open_in_new_tab'))) {
                $validator->errors()->add('open_in_new_tab', 'The open in new tab field must be true or false.');
            }

            if (! $this->has('type')) {
                return;
            }

            $type = $this->input('type');

            if ($type === NavigationItemType::Page->value) {
                if (! $this->has('page_id') || $this->input('page_id') === null) {
                    $validator->errors()->add('page_id', 'The page id field is required.');
                }

                if ($this->has('url')) {
                    $validator->errors()->add('url', 'This field is not allowed.');
                }
            }

            if ($type === NavigationItemType::Url->value) {
                if (! $this->has('url') || ! is_string($this->input('url')) || $this->input('url') === '') {
                    $validator->errors()->add('url', 'The url field is required.');
                }

                if ($this->has('page_id')) {
                    $validator->errors()->add('page_id', 'This field is not allowed.');
                }
            }

            if ($validator->errors()->isNotEmpty() || ! $this->has('page_id')) {
                return;
            }

            $website = $this->route('website');

            if (! $website instanceof Website) {
                return;
            }

            $pageId = $this->integer('page_id');
            $page = Page::query()->find($pageId);

            if ($page === null || $page->trashed() || (int) $page->website_id !== (int) $website->id) {
                $validator->errors()->add('page_id', 'The selected page is invalid.');
            }
        });
    }

    public function itemType(): NavigationItemType
    {
        return NavigationItemType::from($this->string('type')->toString());
    }

    public function openInNewTab(): bool
    {
        if (! $this->has('open_in_new_tab')) {
            return false;
        }

        return (bool) $this->boolean('open_in_new_tab');
    }

    /**
     * @return array{
     *     type: NavigationItemType,
     *     page_id?: int|null,
     *     url?: string|null,
     *     label?: string|null,
     *     parent_public_id?: string|null,
     *     open_in_new_tab: bool
     * }
     */
    public function itemPayload(): array
    {
        $type = $this->itemType();

        $payload = [
            'type' => $type,
            'label' => $this->filled('label') ? $this->string('label')->toString() : null,
            'parent_public_id' => $this->has('parent_id') ? $this->input('parent_id') : null,
            'open_in_new_tab' => $this->openInNewTab(),
        ];

        if ($type === NavigationItemType::Page) {
            $payload['page_id'] = $this->integer('page_id');
        }

        if ($type === NavigationItemType::Url) {
            $payload['url'] = $this->string('url')->toString();
        }

        return $payload;
    }
}
