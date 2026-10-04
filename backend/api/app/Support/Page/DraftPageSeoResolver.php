<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\PageVersionSeoSetting;

class DraftPageSeoResolver
{
    /**
     * @return array<string, mixed>
     */
    public function resolveForPage(Page $page): array
    {
        $draft = $this->resolveCurrentDraft($page);

        $seo = PageVersionSeoSetting::query()
            ->where('page_version_id', $draft->id)
            ->first();

        return PageSeoEffectiveState::fromSeoRow($seo);
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
