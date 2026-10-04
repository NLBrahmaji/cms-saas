<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationVersion;
use App\Models\User;

/**
 * Returns the NavigationVersion future mutation code may edit.
 *
 * Callers are expected to lock the Navigation aggregate before calling prepare(),
 * consistent with PageEditableDraftPreparer. This service does not acquire locks.
 *
 * Snapshot cloning and draft pointer assignment are not wrapped in a transaction
 * here; callers should run prepare() inside their own DB transaction when needed.
 */
class NavigationEditableDraftPreparer
{
    public function __construct(
        private readonly NavigationVersionSnapshotCloner $snapshotCloner,
    ) {}

    public function prepare(Navigation $navigation, User $user): NavigationVersion
    {
        $navigation->refresh();

        if ($navigation->draft_version_id === null) {
            throw new NavigationMissingDraftException;
        }

        $draft = NavigationVersion::query()->find($navigation->draft_version_id);

        if ($draft === null || (int) $draft->navigation_id !== (int) $navigation->id) {
            throw new NavigationMissingDraftException;
        }

        $publishedVersionId = $navigation->published_version_id;

        if ($publishedVersionId === null || (int) $draft->id !== (int) $publishedVersionId) {
            return $draft;
        }

        $newVersion = $this->snapshotCloner->cloneToNewDraftVersion($navigation, $draft, $user);
        $navigation->assignDraftVersion($newVersion);

        return $newVersion;
    }
}
