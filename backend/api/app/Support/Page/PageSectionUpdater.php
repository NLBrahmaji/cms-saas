<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PageSectionUpdater
{
    public function __construct(
        private readonly EditablePageSectionResolver $sectionResolver,
        private readonly PageEditableDraftPreparer $draftPreparer,
    ) {}

    /**
     * @param  array{settings?: array<string, mixed>, is_visible?: bool}  $changes
     */
    public function update(Page $page, User $user, string $publicId, array $changes): PageSection
    {
        return DB::transaction(function () use ($page, $user, $publicId, $changes): PageSection {
            $lockedPage = Page::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->resolveCurrentDraft($lockedPage);
            $section = $this->sectionResolver->resolve($draft, $publicId);

            if ($this->isNoOp($section, $changes)) {
                return $section;
            }

            $editableDraft = $this->draftPreparer->prepare($lockedPage, $user);
            $section = $this->sectionResolver->resolve($editableDraft, $publicId);

            $this->applyChanges($section, $changes);

            return $section->refresh()->load(['template.sectionType', 'content']);
        });
    }

    /**
     * @param  array{settings?: array<string, mixed>, is_visible?: bool}  $changes
     */
    private function isNoOp(PageSection $section, array $changes): bool
    {
        if (array_key_exists('settings', $changes)) {
            $currentSettings = PageJsonDocument::normalizeForComparison($section->settings);

            if (! PageJsonDocument::equals($currentSettings, $changes['settings'])) {
                return false;
            }
        }

        if (array_key_exists('is_visible', $changes)) {
            if ($section->is_visible !== $changes['is_visible']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{settings?: array<string, mixed>, is_visible?: bool}  $changes
     */
    private function applyChanges(PageSection $section, array $changes): void
    {
        $payload = [];

        if (array_key_exists('settings', $changes)) {
            $payload['settings'] = $changes['settings'];
        }

        if (array_key_exists('is_visible', $changes)) {
            $payload['is_visible'] = $changes['is_visible'];
        }

        if ($payload !== []) {
            $section->update($payload);
        }
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
