<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\Website;
use App\Navigation\NavigationItemType;
use App\Rules\NavigationItemUrl;
use Illuminate\Support\Collection;

class NavigationPublishSnapshotValidator
{
    public function assertPublishable(Navigation $navigation, Website $website, NavigationVersion $draft): void
    {
        if ((int) $navigation->website_id !== (int) $website->id) {
            InvalidNavigationPublishSnapshotException::throw('The navigation does not belong to this website.');
        }

        $items = NavigationItemDraftTree::loadVersionItems($draft);

        try {
            NavigationItemDraftTree::assertValid($items);
        } catch (InvalidNavigationVersionItemTreeException $exception) {
            throw $exception;
        }

        if ($items->isEmpty()) {
            return;
        }

        $pageIds = $items
            ->filter(fn (NavigationItem $item): bool => $item->type === NavigationItemType::Page)
            ->pluck('page_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $pagesById = $this->loadPagesById($pageIds);
        $publishedVersionsById = $this->loadPublishedVersionsForPages($pagesById);

        foreach ($items as $item) {
            $this->assertItemPublishable($item, $website, $pagesById, $publishedVersionsById);
        }
    }

    /**
     * @param  list<int>  $pageIds
     * @return Collection<int, Page>
     */
    private function loadPagesById(array $pageIds): Collection
    {
        if ($pageIds === []) {
            return collect();
        }

        return Page::withTrashed()
            ->whereIn('id', $pageIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Page>  $pagesById
     * @return Collection<int, PageVersion>
     */
    private function loadPublishedVersionsForPages(Collection $pagesById): Collection
    {
        $publishedVersionIds = $pagesById
            ->pluck('published_version_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($publishedVersionIds === []) {
            return collect();
        }

        return PageVersion::query()
            ->whereIn('id', $publishedVersionIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Page>  $pagesById
     * @param  Collection<int, PageVersion>  $publishedVersionsById
     */
    private function assertItemPublishable(
        NavigationItem $item,
        Website $website,
        Collection $pagesById,
        Collection $publishedVersionsById,
    ): void {
        if (! in_array($item->type, NavigationItemType::supported(), true)) {
            InvalidNavigationPublishSnapshotException::throw('The navigation contains an unsupported item type.');
        }

        if ($item->type === NavigationItemType::Page) {
            $this->assertPageItemPublishable($item, $website, $pagesById, $publishedVersionsById);

            return;
        }

        $this->assertUrlItemPublishable($item);
    }

    /**
     * @param  Collection<int, Page>  $pagesById
     * @param  Collection<int, PageVersion>  $publishedVersionsById
     */
    private function assertPageItemPublishable(
        NavigationItem $item,
        Website $website,
        Collection $pagesById,
        Collection $publishedVersionsById,
    ): void {
        if ($item->page_id === null) {
            InvalidNavigationPublishSnapshotException::throw('A page navigation item is missing its page target.');
        }

        if ($item->url !== null) {
            InvalidNavigationPublishSnapshotException::throw('A page navigation item has an invalid target state.');
        }

        $page = $pagesById->get($item->page_id);

        if ($page === null) {
            InvalidNavigationPublishSnapshotException::throw('A page navigation item references an invalid page target.');
        }

        if ($page->trashed()) {
            InvalidNavigationPublishSnapshotException::throw('A page navigation item references a deleted page target.');
        }

        if ((int) $page->website_id !== (int) $website->id) {
            InvalidNavigationPublishSnapshotException::throw('A page navigation item references a page that does not belong to this website.');
        }

        if ($page->published_version_id === null) {
            InvalidNavigationPublishSnapshotException::throw('A page navigation item references an unpublished page target.');
        }

        $publishedVersion = $publishedVersionsById->get($page->published_version_id);

        if ($publishedVersion === null || (int) $publishedVersion->page_id !== (int) $page->id) {
            InvalidNavigationPublishSnapshotException::throw('A page navigation item references a page with an invalid published version.');
        }
    }

    private function assertUrlItemPublishable(NavigationItem $item): void
    {
        if ($item->page_id !== null) {
            InvalidNavigationPublishSnapshotException::throw('A URL navigation item has an invalid target state.');
        }

        if ($item->url === null || $item->url === '') {
            InvalidNavigationPublishSnapshotException::throw('A URL navigation item is missing its URL target.');
        }

        $messages = [];
        $rule = new NavigationItemUrl;
        $rule->validate('url', $item->url, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        if ($messages !== []) {
            InvalidNavigationPublishSnapshotException::throw('A URL navigation item has an invalid URL.');
        }
    }
}
