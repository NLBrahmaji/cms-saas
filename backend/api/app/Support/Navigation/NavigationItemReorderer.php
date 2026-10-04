<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NavigationItemReorderer
{
    public function __construct(
        private readonly DraftNavigationVersionResolver $draftResolver,
        private readonly NavigationEditableDraftPreparer $draftPreparer,
        private readonly EditableNavigationItemResolver $itemResolver,
    ) {}

    /**
     * @param  list<string>  $itemIds
     */
    public function reorder(
        Navigation $navigation,
        User $user,
        ?string $parentPublicId,
        array $itemIds,
    ): void {
        DB::transaction(function () use ($navigation, $user, $parentPublicId, $itemIds): void {
            $lockedNavigation = Navigation::query()
                ->whereKey($navigation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentDraft = $this->draftResolver->resolve($lockedNavigation);

            $this->resolveParentOnDraft($currentDraft, $parentPublicId);

            $siblings = $this->loadOrderedSiblings($currentDraft, $parentPublicId);

            $this->assertCollectionMatches($siblings, $itemIds);

            $effectiveOrder = $siblings->pluck('public_id')->all();

            if ($itemIds === $effectiveOrder) {
                return;
            }

            $editableDraft = $this->draftPreparer->prepare($lockedNavigation, $user);

            $this->resolveParentOnDraft($editableDraft, $parentPublicId);

            $siblings = $this->loadOrderedSiblings($editableDraft, $parentPublicId);

            $this->assertCollectionMatches($siblings, $itemIds);

            $siblingsByPublicId = $siblings->keyBy('public_id');

            foreach ($itemIds as $index => $publicId) {
                $item = $siblingsByPublicId->get($publicId);

                if ($item === null) {
                    InvalidNavigationItemOrderException::throw();
                }

                if ((int) $item->sort_order !== $index) {
                    $item->update(['sort_order' => $index]);
                }
            }
        });
    }

    private function resolveParentOnDraft(NavigationVersion $draft, ?string $parentPublicId): void
    {
        if ($parentPublicId === null) {
            return;
        }

        try {
            $this->itemResolver->resolve($draft, $parentPublicId);
        } catch (ModelNotFoundException) {
            InvalidNavigationItemParentException::throw();
        }
    }

    /**
     * @return Collection<int, NavigationItem>
     */
    private function loadOrderedSiblings(NavigationVersion $draft, ?string $parentPublicId): Collection
    {
        $query = NavigationItem::query()
            ->where('navigation_version_id', $draft->id)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($parentPublicId === null) {
            $query->whereNull('parent_id');
        } else {
            $parent = $this->itemResolver->resolve($draft, $parentPublicId);
            $query->where('parent_id', $parent->id);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, NavigationItem>  $siblings
     * @param  list<string>  $itemIds
     */
    private function assertCollectionMatches(Collection $siblings, array $itemIds): void
    {
        $currentIds = $siblings->pluck('public_id')->all();

        if (count($itemIds) !== count($currentIds)) {
            InvalidNavigationItemOrderException::throw();
        }

        $currentLookup = array_flip($currentIds);

        foreach ($itemIds as $publicId) {
            if (! array_key_exists($publicId, $currentLookup)) {
                InvalidNavigationItemOrderException::throw();
            }
        }
    }
}
