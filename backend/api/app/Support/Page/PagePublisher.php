<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PagePublisher
{
    public function publish(Website $website, Page $page, User $user): Page
    {
        return DB::transaction(function () use ($website, $page, $user): Page {
            Website::query()
                ->whereKey($website->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPage = Page::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->resolveDraft($lockedPage);

            if ($this->isIdempotentPublish($lockedPage, $draft)) {
                return $lockedPage->load('draftVersion');
            }

            $this->assertPublishableDraft($lockedPage, $website, $draft);

            if ($draft->published_at === null) {
                $draft->published_at = now();
                $draft->published_by = $user->id;
                $draft->save();
            }

            $lockedPage->assignPublishedVersion($draft);
            $lockedPage->assignDraftVersion($draft);

            return $lockedPage->refresh()->load('draftVersion');
        });
    }

    private function resolveDraft(Page $page): PageVersion
    {
        if ($page->draft_version_id === null) {
            throw new PageMissingDraftException;
        }

        $draft = PageVersion::query()->find($page->draft_version_id);

        if ($draft === null || (int) $draft->page_id !== (int) $page->id) {
            throw new PageMissingDraftException;
        }

        return $draft;
    }

    private function isIdempotentPublish(Page $page, PageVersion $draft): bool
    {
        return $page->published_version_id !== null
            && (int) $page->published_version_id === (int) $draft->id
            && $draft->published_at !== null;
    }

    private function assertPublishableDraft(Page $page, Website $website, PageVersion $draft): void
    {
        if ($draft->is_home) {
            throw ValidationException::withMessages([
                'is_home' => ['Publishing a homepage is not supported yet.'],
            ]);
        }

        if ($draft->parent_page_id !== null) {
            $this->assertValidParent($page, (int) $draft->parent_page_id);
        }

        if ($this->publishedSlugConflictsWithAnotherPage($website, $page, $draft->slug)) {
            throw ValidationException::withMessages([
                'slug' => ['This slug is already used by another published page on this website.'],
            ]);
        }
    }

    private function assertValidParent(Page $page, int $parentPageId): void
    {
        if ($parentPageId === (int) $page->id) {
            throw ValidationException::withMessages([
                'parent_page_id' => ['The selected parent page is invalid.'],
            ]);
        }

        $parent = Page::query()->find($parentPageId);

        if ($parent === null) {
            throw ValidationException::withMessages([
                'parent_page_id' => ['The selected parent page is invalid.'],
            ]);
        }

        if ($parent->trashed()) {
            throw ValidationException::withMessages([
                'parent_page_id' => ['The selected parent page is invalid.'],
            ]);
        }

        if ((int) $parent->website_id !== (int) $page->website_id) {
            throw ValidationException::withMessages([
                'parent_page_id' => ['The selected parent page is invalid.'],
            ]);
        }
    }

    private function publishedSlugConflictsWithAnotherPage(Website $website, Page $page, string $slug): bool
    {
        return Page::query()
            ->where('website_id', $website->id)
            ->whereKeyNot($page->id)
            ->whereNotNull('published_version_id')
            ->whereHas('publishedVersion', function ($query) use ($slug): void {
                $query->where('slug', $slug);
            })
            ->exists();
    }
}
