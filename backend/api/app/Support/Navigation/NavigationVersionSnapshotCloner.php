<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\User;
use Illuminate\Support\Collection;

class NavigationVersionSnapshotCloner
{
    public function cloneToNewDraftVersion(Navigation $navigation, NavigationVersion $source, User $user): NavigationVersion
    {
        if ((int) $source->navigation_id !== (int) $navigation->id) {
            throw new \InvalidArgumentException('Source version must belong to this navigation.');
        }

        $sourceItems = NavigationItem::query()
            ->where('navigation_version_id', $source->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->assertValidSourceItemTree($sourceItems);

        $nextVersion = (int) NavigationVersion::query()
            ->where('navigation_id', $navigation->id)
            ->max('version') + 1;

        $newVersion = NavigationVersion::query()->create([
            'navigation_id' => $navigation->id,
            'version' => $nextVersion,
            'created_by' => $user->id,
            'published_by' => null,
            'published_at' => null,
        ]);

        if ($sourceItems->isEmpty()) {
            return $newVersion;
        }

        /** @var array<int, int> $idMap */
        $idMap = [];

        foreach ($sourceItems as $sourceItem) {
            $clone = NavigationItem::query()->create([
                'public_id' => $sourceItem->public_id,
                'navigation_version_id' => $newVersion->id,
                'parent_id' => null,
                'type' => $sourceItem->type,
                'page_id' => $sourceItem->page_id,
                'label' => $sourceItem->label,
                'url' => $sourceItem->url,
                'sort_order' => $sourceItem->sort_order,
                'open_in_new_tab' => $sourceItem->open_in_new_tab,
            ]);

            $idMap[(int) $sourceItem->id] = (int) $clone->id;
        }

        foreach ($sourceItems as $sourceItem) {
            if ($sourceItem->parent_id === null) {
                continue;
            }

            $cloneId = $idMap[(int) $sourceItem->id];
            $newParentId = $idMap[(int) $sourceItem->parent_id];

            NavigationItem::query()
                ->whereKey($cloneId)
                ->update(['parent_id' => $newParentId]);
        }

        return $newVersion;
    }

    /**
     * @param  Collection<int, NavigationItem>  $sourceItems
     */
    private function assertValidSourceItemTree(Collection $sourceItems): void
    {
        if ($sourceItems->isEmpty()) {
            return;
        }

        /** @var array<int, NavigationItem> $itemsById */
        $itemsById = $sourceItems->keyBy('id')->all();

        foreach ($sourceItems as $item) {
            $itemId = (int) $item->id;

            if ($item->parent_id === null) {
                continue;
            }

            $parentId = (int) $item->parent_id;

            if ($parentId === $itemId) {
                throw new InvalidNavigationVersionItemTreeException('Navigation item cannot be its own parent.');
            }

            if (! array_key_exists($parentId, $itemsById)) {
                throw new InvalidNavigationVersionItemTreeException('Navigation item parent must belong to the same version.');
            }
        }

        foreach ($sourceItems as $item) {
            $visited = [];
            $currentId = (int) $item->id;

            while (true) {
                if (isset($visited[$currentId])) {
                    throw new InvalidNavigationVersionItemTreeException('Navigation item tree contains a cycle.');
                }

                $visited[$currentId] = true;
                $current = $itemsById[$currentId];
                $parentId = $current->parent_id;

                if ($parentId === null) {
                    break;
                }

                $parentId = (int) $parentId;

                if (! array_key_exists($parentId, $itemsById)) {
                    throw new InvalidNavigationVersionItemTreeException('Navigation item parent must belong to the same version.');
                }

                $currentId = $parentId;
            }
        }
    }
}
