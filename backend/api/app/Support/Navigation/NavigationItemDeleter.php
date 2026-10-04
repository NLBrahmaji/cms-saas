<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NavigationItemDeleter
{
    public function __construct(
        private readonly DraftNavigationVersionResolver $draftResolver,
        private readonly NavigationEditableDraftPreparer $draftPreparer,
        private readonly EditableNavigationItemResolver $itemResolver,
    ) {}

    public function delete(Navigation $navigation, User $user, string $publicId): void
    {
        DB::transaction(function () use ($navigation, $user, $publicId): void {
            $lockedNavigation = Navigation::query()
                ->whereKey($navigation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentDraft = $this->draftResolver->resolve($lockedNavigation);
            $target = $this->itemResolver->resolve($currentDraft, $publicId);

            $draftItems = NavigationItemDraftTree::loadVersionItems($currentDraft);
            $subtreePublicIds = NavigationItemDraftTree::subtreePublicIdsPostOrder($target, $draftItems);

            $editableDraft = $this->draftPreparer->prepare($lockedNavigation, $user);

            foreach ($subtreePublicIds as $subtreePublicId) {
                $item = $this->itemResolver->resolve($editableDraft, $subtreePublicId);
                $item->delete();
            }
        });
    }
}
