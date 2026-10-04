<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageSectionContent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PageSectionCreator
{
    public function __construct(
        private readonly PageEditableDraftPreparer $draftPreparer,
    ) {}

    public function create(Page $page, User $user, int $sectionTemplateId): PageSection
    {
        return DB::transaction(function () use ($page, $user, $sectionTemplateId): PageSection {
            $lockedPage = Page::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->draftPreparer->prepare($lockedPage, $user);

            $maxSortOrder = PageSection::query()
                ->where('page_version_id', $draft->id)
                ->max('sort_order');

            $sortOrder = $maxSortOrder === null ? 0 : (int) $maxSortOrder + 1;

            $section = PageSection::query()->create([
                'page_version_id' => $draft->id,
                'section_template_id' => $sectionTemplateId,
                'sort_order' => $sortOrder,
                'is_visible' => true,
                'settings' => [],
            ]);

            PageSectionContent::query()->create([
                'page_section_id' => $section->id,
                'content' => [],
            ]);

            return $section->load(['template.sectionType', 'content']);
        });
    }
}
