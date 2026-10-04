<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageSectionContent;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PageSectionContentUpdater
{
    public function __construct(
        private readonly EditablePageSectionResolver $sectionResolver,
        private readonly PageEditableDraftPreparer $draftPreparer,
    ) {}

    /**
     * @param  array<string, mixed>  $content
     */
    public function update(Page $page, User $user, string $publicId, array $content): PageSection
    {
        return DB::transaction(function () use ($page, $user, $publicId, $content): PageSection {
            $lockedPage = Page::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->resolveCurrentDraft($lockedPage);
            $section = $this->sectionResolver->resolve($draft, $publicId);

            $currentContent = PageJsonDocument::normalizeForComparison($section->content?->content);

            if (PageJsonDocument::equals($currentContent, $content)) {
                return $section;
            }

            $editableDraft = $this->draftPreparer->prepare($lockedPage, $user);
            $section = $this->sectionResolver->resolve($editableDraft, $publicId);

            $this->persistContent($section, $content);

            return $section->refresh()->load(['template.sectionType', 'content']);
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

    /**
     * @param  array<string, mixed>  $content
     */
    private function persistContent(PageSection $section, array $content): void
    {
        $existing = $section->content;

        if ($existing !== null) {
            $existing->update(['content' => $content]);

            return;
        }

        PageSectionContent::query()->create([
            'page_section_id' => $section->id,
            'content' => $content,
        ]);
    }
}
