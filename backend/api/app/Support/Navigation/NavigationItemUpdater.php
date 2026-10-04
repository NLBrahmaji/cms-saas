<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\User;
use App\Models\Website;
use App\Navigation\NavigationItemType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NavigationItemUpdater
{
    public function __construct(
        private readonly DraftNavigationVersionResolver $draftResolver,
        private readonly NavigationEditableDraftPreparer $draftPreparer,
        private readonly EditableNavigationItemResolver $itemResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(
        Navigation $navigation,
        Website $website,
        User $user,
        string $publicId,
        array $changes,
    ): NavigationItem {
        return DB::transaction(function () use ($navigation, $website, $user, $publicId, $changes): NavigationItem {
            $lockedNavigation = Navigation::query()
                ->whereKey($navigation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentDraft = $this->draftResolver->resolve($lockedNavigation);
            $item = $this->itemResolver->resolve($currentDraft, $publicId);

            $baseline = NavigationItemEffectiveState::fromItem($item);
            $effective = NavigationItemEffectiveState::fromItemWithChanges($item, $changes);

            $effective->validate($website);

            $this->assertValidParentChange($currentDraft, $item, $effective, $changes);

            if ($effective->matchesItem($item)) {
                return $item->load('parent');
            }

            $editableDraft = $this->draftPreparer->prepare($lockedNavigation, $user);
            $item = $this->itemResolver->resolve($editableDraft, $publicId);

            $parentNumericId = $this->resolveParentNumericId($editableDraft, $effective->parentPublicId);
            $payload = [
                'type' => $effective->type,
                'page_id' => $effective->type === NavigationItemType::Page ? $effective->pageId : null,
                'url' => $effective->type === NavigationItemType::Url ? $effective->url : null,
                'label' => $effective->label,
                'parent_id' => $parentNumericId,
                'open_in_new_tab' => $effective->openInNewTab,
            ];

            if ($effective->parentChangedFrom($baseline)) {
                $payload['sort_order'] = $this->nextSiblingSortOrder(
                    $editableDraft,
                    $parentNumericId,
                    $item->id,
                );
            }

            $item->update($payload);

            return $item->refresh()->load('parent');
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function assertValidParentChange(
        NavigationVersion $draft,
        NavigationItem $item,
        NavigationItemEffectiveState $effective,
        array $changes,
    ): void {
        if (! array_key_exists('parent_id', $changes) && $effective->parentPublicId === $item->parent?->public_id) {
            return;
        }

        $parentPublicId = $effective->parentPublicId;

        if ($parentPublicId === null) {
            return;
        }

        if ($parentPublicId === $item->public_id) {
            throw ValidationException::withMessages([
                'parent_id' => ['The selected parent item is invalid.'],
            ]);
        }

        try {
            $proposedParent = $this->itemResolver->resolve($draft, $parentPublicId);
        } catch (ModelNotFoundException) {
            InvalidNavigationItemParentException::throw();
        }

        $this->assertParentIsNotDescendantOfItem($draft, $item, $proposedParent);
    }

    private function assertParentIsNotDescendantOfItem(
        NavigationVersion $draft,
        NavigationItem $item,
        NavigationItem $proposedParent,
    ): void {
        /** @var Collection<int, NavigationItem> $itemsById */
        $itemsById = NavigationItem::query()
            ->where('navigation_version_id', $draft->id)
            ->get()
            ->keyBy('id');

        $walker = $proposedParent;

        while ($walker !== null) {
            if ((int) $walker->id === (int) $item->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['The selected parent item is invalid.'],
                ]);
            }

            if ($walker->parent_id === null) {
                break;
            }

            $walker = $itemsById->get($walker->parent_id);
        }
    }

    private function resolveParentNumericId(NavigationVersion $draft, ?string $parentPublicId): ?int
    {
        if ($parentPublicId === null) {
            return null;
        }

        return $this->itemResolver->resolve($draft, $parentPublicId)->id;
    }

    private function nextSiblingSortOrder(NavigationVersion $draft, ?int $parentNumericId, int $excludeItemId): int
    {
        $query = NavigationItem::query()
            ->where('navigation_version_id', $draft->id)
            ->whereKeyNot($excludeItemId);

        if ($parentNumericId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentNumericId);
        }

        $maxSortOrder = $query->max('sort_order');

        return $maxSortOrder === null ? 0 : (int) $maxSortOrder + 1;
    }
}
