<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationVersion;

class DraftNavigationVersionResolver
{
    public function resolve(Navigation $navigation): NavigationVersion
    {
        if ($navigation->draft_version_id === null) {
            throw new NavigationMissingDraftException;
        }

        $draft = NavigationVersion::query()->find($navigation->draft_version_id);

        if ($draft === null || (int) $draft->navigation_id !== (int) $navigation->id) {
            throw new NavigationMissingDraftException;
        }

        return $draft;
    }
}
