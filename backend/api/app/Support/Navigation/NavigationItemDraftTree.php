<?php

namespace App\Support\Navigation;

use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use Illuminate\Support\Collection;

final class NavigationItemDraftTree
{
    /**
     * @return Collection<int, NavigationItem>
     */
    public static function loadVersionItems(NavigationVersion $version): Collection
    {
        return NavigationItem::query()
            ->where('navigation_version_id', $version->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, NavigationItem>  $items
     */
    public static function assertValid(Collection $items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        /** @var array<int, NavigationItem> $itemsById */
        $itemsById = $items->keyBy('id')->all();

        foreach ($items as $item) {
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

        foreach ($items as $item) {
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

    /**
     * @param  Collection<int, NavigationItem>  $items
     * @return list<string>
     */
    public static function subtreePublicIdsPostOrder(NavigationItem $root, Collection $items): array
    {
        self::assertValid($items);

        /** @var array<int, list<NavigationItem>> $childrenByParentId */
        $childrenByParentId = [];

        foreach ($items as $item) {
            if ($item->parent_id === null) {
                continue;
            }

            $childrenByParentId[(int) $item->parent_id][] = $item;
        }

        $result = [];
        $visiting = [];

        self::visitSubtreePostOrder((int) $root->id, $childrenByParentId, $visiting, $result);

        /** @var array<int, NavigationItem> $itemsById */
        $itemsById = $items->keyBy('id')->all();

        if (! array_key_exists((int) $root->id, $itemsById)) {
            throw new InvalidNavigationVersionItemTreeException('Navigation item parent must belong to the same version.');
        }

        $result[] = $root->public_id;

        return $result;
    }

    /**
     * @param  array<int, list<NavigationItem>>  $childrenByParentId
     * @param  array<int, true>  $visiting
     * @param  list<string>  $result
     */
    private static function visitSubtreePostOrder(
        int $itemId,
        array $childrenByParentId,
        array &$visiting,
        array &$result,
    ): void {
        if (isset($visiting[$itemId])) {
            throw new InvalidNavigationVersionItemTreeException('Navigation item tree contains a cycle.');
        }

        $visiting[$itemId] = true;

        foreach ($childrenByParentId[$itemId] ?? [] as $child) {
            self::visitSubtreePostOrder((int) $child->id, $childrenByParentId, $visiting, $result);
            $result[] = $child->public_id;
        }

        unset($visiting[$itemId]);
    }
}
