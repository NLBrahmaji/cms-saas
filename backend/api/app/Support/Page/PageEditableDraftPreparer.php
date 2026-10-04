<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;

class PageEditableDraftPreparer
{
    public function __construct(
        private readonly PageVersionSnapshotCloner $snapshotCloner,
    ) {}

    public function prepare(Page $page, User $user): PageVersion
    {
        $page->refresh();

        if ($page->draft_version_id === null) {
            throw new PageMissingDraftException;
        }

        $draft = PageVersion::query()->find($page->draft_version_id);

        if ($draft === null || (int) $draft->page_id !== (int) $page->id) {
            throw new PageMissingDraftException;
        }

        $publishedVersionId = $page->published_version_id;

        if ($publishedVersionId === null || (int) $draft->id !== (int) $publishedVersionId) {
            return $draft;
        }

        $newVersion = $this->snapshotCloner->cloneToNewDraftVersion($page, $draft, $user);
        $page->assignDraftVersion($newVersion);

        return $newVersion;
    }
}
