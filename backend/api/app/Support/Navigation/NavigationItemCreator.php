<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\User;
use App\Navigation\NavigationItemType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class NavigationItemCreator
{
    public function __construct(
        private readonly DraftNavigationVersionResolver $draftResolver,
        private readonly NavigationEditableDraftPreparer $draftPreparer,
        private readonly EditableNavigationItemResolver $itemResolver,
    ) {}

    /**
     * @param  array{
     *     type: NavigationItemType,
     *     page_id?: int|null,
     *     url?: string|null,
     *     label?: string|null,
     *     parent_public_id?: string|null,
     *     open_in_new_tab: bool
     * }  $payload
     */
    public function create(Navigation $navigation, User $user, array $payload): NavigationItem
    {
        return DB::transaction(function () use ($navigation, $user, $payload): NavigationItem {
            $lockedNavigation = Navigation::query()
                ->whereKey($navigation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentDraft = $this->draftResolver->resolve($lockedNavigation);

            $parentPublicId = $payload['parent_public_id'] ?? null;

            if ($parentPublicId !== null && $parentPublicId !== '') {
                try {
                    $this->itemResolver->resolve($currentDraft, $parentPublicId);
                } catch (ModelNotFoundException) {
                    InvalidNavigationItemParentException::throw();
                }
            }

            $editableDraft = $this->draftPreparer->prepare($lockedNavigation, $user);

            $parentNumericId = null;

            if ($parentPublicId !== null && $parentPublicId !== '') {
                $parentItem = $this->itemResolver->resolve($editableDraft, $parentPublicId);
                $parentNumericId = $parentItem->id;
            }

            $sortOrder = $this->nextSiblingSortOrder($editableDraft, $parentNumericId);

            $item = NavigationItem::query()->create([
                'navigation_version_id' => $editableDraft->id,
                'parent_id' => $parentNumericId,
                'type' => $payload['type'],
                'page_id' => $payload['type'] === NavigationItemType::Page ? $payload['page_id'] : null,
                'url' => $payload['type'] === NavigationItemType::Url ? $payload['url'] : null,
                'label' => $payload['label'] ?? null,
                'sort_order' => $sortOrder,
                'open_in_new_tab' => $payload['open_in_new_tab'],
            ]);

            return $item->load('parent');
        });
    }

    private function nextSiblingSortOrder(NavigationVersion $draft, ?int $parentNumericId): int
    {
        $query = NavigationItem::query()
            ->where('navigation_version_id', $draft->id);

        if ($parentNumericId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentNumericId);
        }

        $maxSortOrder = $query->max('sort_order');

        return $maxSortOrder === null ? 0 : (int) $maxSortOrder + 1;
    }
}
