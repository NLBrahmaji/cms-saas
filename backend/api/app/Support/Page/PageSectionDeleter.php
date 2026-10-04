<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PageSectionDeleter
{
    public function __construct(
        private readonly EditablePageSectionResolver $sectionResolver,
        private readonly PageEditableDraftPreparer $draftPreparer,
    ) {}

    public function delete(Page $page, User $user, string $publicId): void
    {
        DB::transaction(function () use ($page, $user, $publicId): void {
            $lockedPage = Page::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->resolveCurrentDraft($lockedPage);
            $this->sectionResolver->resolve($draft, $publicId);

            $editableDraft = $this->draftPreparer->prepare($lockedPage, $user);
            $section = $this->sectionResolver->resolve($editableDraft, $publicId);

            $section->delete();
        });
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
