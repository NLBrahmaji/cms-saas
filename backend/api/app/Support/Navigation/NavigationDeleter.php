<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationVersion;
use Illuminate\Support\Facades\DB;

class NavigationDeleter
{
    public function delete(Navigation $navigation): void
    {
        DB::transaction(function () use ($navigation): void {
            $lockedNavigation = Navigation::query()
                ->whereKey($navigation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedNavigation->draft_version_id = null;
            $lockedNavigation->published_version_id = null;
            $lockedNavigation->saveQuietly();

            $versions = NavigationVersion::query()
                ->where('navigation_id', $lockedNavigation->id)
                ->orderBy('id')
                ->get();

            foreach ($versions as $version) {
                $version->delete();
            }

            $lockedNavigation->delete();
        });
    }
}
