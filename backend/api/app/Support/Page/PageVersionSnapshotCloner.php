<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageSectionContent;
use App\Models\PageVersion;
use App\Models\PageVersionSeoSetting;
use App\Models\User;

class PageVersionSnapshotCloner
{
    public function cloneToNewDraftVersion(Page $page, PageVersion $source, User $user): PageVersion
    {
        if ((int) $source->page_id !== (int) $page->id) {
            throw new \InvalidArgumentException('Source version must belong to this page.');
        }

        $nextVersion = (int) PageVersion::query()
            ->where('page_id', $page->id)
            ->max('version') + 1;

        $newVersion = PageVersion::query()->create([
            'page_id' => $page->id,
            'version' => $nextVersion,
            'name' => $source->name,
            'slug' => $source->slug,
            'parent_page_id' => $source->parent_page_id,
            'created_by' => $user->id,
            'published_by' => null,
            'published_at' => null,
        ]);

        $this->cloneSeoSettings($source, $newVersion);
        $this->cloneSectionsAndContents($source, $newVersion);

        return $newVersion;
    }

    private function cloneSeoSettings(PageVersion $source, PageVersion $target): void
    {
        $seo = PageVersionSeoSetting::query()
            ->where('page_version_id', $source->id)
            ->first();

        if ($seo === null) {
            return;
        }

        PageVersionSeoSetting::query()->create([
            'page_version_id' => $target->id,
            'meta_title' => $seo->meta_title,
            'meta_description' => $seo->meta_description,
            'og_title' => $seo->og_title,
            'og_description' => $seo->og_description,
            'og_image_id' => $seo->og_image_id,
            'canonical_url' => $seo->canonical_url,
            'robots_index' => $seo->robots_index,
            'robots_follow' => $seo->robots_follow,
        ]);
    }

    private function cloneSectionsAndContents(PageVersion $source, PageVersion $target): void
    {
        $sections = PageSection::query()
            ->where('page_version_id', $source->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($sections as $section) {
            $newSection = PageSection::query()->create([
                'public_id' => $section->public_id,
                'page_version_id' => $target->id,
                'section_template_id' => $section->section_template_id,
                'sort_order' => $section->sort_order,
                'is_visible' => $section->is_visible,
                'settings' => $section->settings,
            ]);

            $content = PageSectionContent::query()
                ->where('page_section_id', $section->id)
                ->first();

            if ($content !== null) {
                PageSectionContent::query()->create([
                    'page_section_id' => $newSection->id,
                    'content' => $content->content,
                ]);
            }
        }
    }
}
