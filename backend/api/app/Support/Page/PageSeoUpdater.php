<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\PageVersionSeoSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PageSeoUpdater
{
    public function __construct(
        private readonly PageEditableDraftPreparer $draftPreparer,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function update(Page $page, User $user, array $changes): array
    {
        return DB::transaction(function () use ($page, $user, $changes): array {
            $lockedPage = Page::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->resolveCurrentDraft($lockedPage);
            $seo = PageVersionSeoSetting::query()
                ->where('page_version_id', $draft->id)
                ->first();

            $effective = PageSeoEffectiveState::fromSeoRow($seo);
            $candidate = PageSeoEffectiveState::applyChanges($effective, $changes);

            if (PageSeoEffectiveState::equals($effective, $candidate)) {
                return $effective;
            }

            $editableDraft = $this->draftPreparer->prepare($lockedPage, $user);

            $seo = PageVersionSeoSetting::query()
                ->where('page_version_id', $editableDraft->id)
                ->first();

            if ($seo === null) {
                PageVersionSeoSetting::query()->create([
                    'page_version_id' => $editableDraft->id,
                    'meta_title' => $candidate['meta_title'],
                    'meta_description' => $candidate['meta_description'],
                    'og_title' => $candidate['og_title'],
                    'og_description' => $candidate['og_description'],
                    'og_image_id' => $candidate['og_image_id'],
                    'canonical_url' => $candidate['canonical_url'],
                    'robots_index' => $candidate['robots_index'],
                    'robots_follow' => $candidate['robots_follow'],
                ]);
            } else {
                $payload = [];

                foreach ($changes as $field => $value) {
                    $payload[$field] = $value;
                }

                if ($payload !== []) {
                    $seo->update($payload);
                }
            }

            $seo = PageVersionSeoSetting::query()
                ->where('page_version_id', $editableDraft->id)
                ->first();

            return PageSeoEffectiveState::fromSeoRow($seo);
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
