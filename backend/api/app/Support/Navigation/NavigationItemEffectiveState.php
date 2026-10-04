<?php

namespace App\Support\Navigation;

use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Website;
use App\Navigation\NavigationItemType;
use App\Rules\NavigationItemUrl;
use Illuminate\Validation\ValidationException;

final class NavigationItemEffectiveState
{
    public function __construct(
        public readonly NavigationItemType $type,
        public readonly ?int $pageId,
        public readonly ?string $url,
        public readonly ?string $label,
        public readonly ?string $parentPublicId,
        public readonly bool $openInNewTab,
    ) {}

    public static function fromItem(NavigationItem $item): self
    {
        $item->loadMissing('parent');

        return new self(
            type: $item->type,
            pageId: $item->page_id,
            url: $item->url,
            label: $item->label,
            parentPublicId: $item->parent?->public_id,
            openInNewTab: (bool) $item->open_in_new_tab,
        );
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function fromItemWithChanges(NavigationItem $item, array $changes): self
    {
        $item->loadMissing('parent');

        $type = array_key_exists('type', $changes)
            ? NavigationItemType::from((string) $changes['type'])
            : $item->type;

        if (array_key_exists('type', $changes) && $type === NavigationItemType::Page && array_key_exists('url', $changes)) {
            throw ValidationException::withMessages([
                'url' => ['This field is not allowed.'],
            ]);
        }

        if (array_key_exists('type', $changes) && $type === NavigationItemType::Url && array_key_exists('page_id', $changes)) {
            throw ValidationException::withMessages([
                'page_id' => ['This field is not allowed.'],
            ]);
        }

        if ($type === NavigationItemType::Page) {
            if (array_key_exists('url', $changes) && ! array_key_exists('type', $changes)) {
                throw ValidationException::withMessages([
                    'url' => ['This field is not allowed.'],
                ]);
            }

            $pageId = array_key_exists('page_id', $changes)
                ? ($changes['page_id'] === null ? null : (int) $changes['page_id'])
                : ($item->type === NavigationItemType::Page ? $item->page_id : null);

            $url = null;
        } else {
            if (array_key_exists('page_id', $changes) && ! array_key_exists('type', $changes)) {
                throw ValidationException::withMessages([
                    'page_id' => ['This field is not allowed.'],
                ]);
            }

            $url = array_key_exists('url', $changes)
                ? ($changes['url'] === null ? null : (string) $changes['url'])
                : ($item->type === NavigationItemType::Url ? $item->url : null);

            $pageId = null;
        }

        $label = array_key_exists('label', $changes)
            ? $changes['label']
            : $item->label;

        $parentPublicId = array_key_exists('parent_id', $changes)
            ? $changes['parent_id']
            : $item->parent?->public_id;

        $openInNewTab = array_key_exists('open_in_new_tab', $changes)
            ? (bool) $changes['open_in_new_tab']
            : (bool) $item->open_in_new_tab;

        return new self(
            type: $type,
            pageId: $pageId,
            url: $url,
            label: is_string($label) ? $label : null,
            parentPublicId: is_string($parentPublicId) ? $parentPublicId : null,
            openInNewTab: $openInNewTab,
        );
    }

    public function matchesItem(NavigationItem $item): bool
    {
        $item->loadMissing('parent');

        return $this->type === $item->type
            && $this->pageId === $item->page_id
            && $this->url === $item->url
            && $this->label === $item->label
            && $this->parentPublicId === $item->parent?->public_id
            && $this->openInNewTab === (bool) $item->open_in_new_tab;
    }

    public function parentChangedFrom(self $baseline): bool
    {
        return $this->parentPublicId !== $baseline->parentPublicId;
    }

    public function validate(Website $website): void
    {
        if ($this->type === NavigationItemType::Page) {
            if ($this->pageId === null) {
                throw ValidationException::withMessages([
                    'page_id' => ['The page id field is required.'],
                ]);
            }

            $page = Page::query()->find($this->pageId);

            if ($page === null || $page->trashed() || (int) $page->website_id !== (int) $website->id) {
                throw ValidationException::withMessages([
                    'page_id' => ['The selected page is invalid.'],
                ]);
            }

            return;
        }

        if ($this->url === null || $this->url === '') {
            throw ValidationException::withMessages([
                'url' => ['The url field is required.'],
            ]);
        }

        $messages = [];
        $rule = new NavigationItemUrl;
        $rule->validate('url', $this->url, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        if ($messages !== []) {
            throw ValidationException::withMessages([
                'url' => [$messages[0]],
            ]);
        }
    }
}
