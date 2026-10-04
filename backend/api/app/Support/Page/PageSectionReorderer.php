<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PageSectionReorderer
{
    public function __construct(
        private readonly PageEditableDraftPreparer $draftPreparer,
    ) {}

    /**
     * @param  list<string>  $sectionIds
     * @return Collection<int, PageSection>
     */
    public function reorder(Page $page, User $user, array $sectionIds): Collection
    {
        return DB::transaction(function () use ($page, $user, $sectionIds): Collection {
            $lockedPage = Page::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->resolveCurrentDraft($lockedPage);
            $sections = $this->loadOrderedSections($draft->id);

            $this->assertCollectionMatches($sections, $sectionIds);

            $effectiveOrder = $sections->pluck('public_id')->all();

            if ($sectionIds === $effectiveOrder) {
                return $sections;
            }

            $editableDraft = $this->draftPreparer->prepare($lockedPage, $user);
            $sections = $this->loadOrderedSections($editableDraft->id);

            $this->assertCollectionMatches($sections, $sectionIds);

            $sectionsByPublicId = $sections->keyBy('public_id');

            foreach ($sectionIds as $index => $publicId) {
                $section = $sectionsByPublicId->get($publicId);

                if ($section === null) {
                    InvalidPageSectionOrderException::throw();
                }

                if ((int) $section->sort_order !== $index) {
                    $section->update(['sort_order' => $index]);
                }
            }

            return $this->loadOrderedSections($editableDraft->id);
        });
    }

    /**
     * @param  Collection<int, PageSection>  $sections
     * @param  list<string>  $sectionIds
     */
    private function assertCollectionMatches(Collection $sections, array $sectionIds): void
    {
        $currentIds = $sections->pluck('public_id')->all();

        if (count($sectionIds) !== count($currentIds)) {
            InvalidPageSectionOrderException::throw();
        }

        $currentLookup = array_flip($currentIds);

        foreach ($sectionIds as $publicId) {
            if (! array_key_exists($publicId, $currentLookup)) {
                InvalidPageSectionOrderException::throw();
            }
        }
    }

    /**
     * @return Collection<int, PageSection>
     */
    private function loadOrderedSections(int $pageVersionId): Collection
    {
        return PageSection::query()
            ->where('page_version_id', $pageVersionId)
            ->with(['template.sectionType', 'content'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function resolveCurrentDraft(Page $page): PageVersion
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
}
