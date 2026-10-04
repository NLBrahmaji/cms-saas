<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationVersion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class NavigationPublisher
{
    public function __construct(
        private readonly DraftNavigationVersionResolver $draftResolver,
        private readonly NavigationPublishSnapshotValidator $snapshotValidator,
    ) {}

    public function publish(Website $website, Navigation $navigation, User $user): Navigation
    {
        return DB::transaction(function () use ($website, $navigation, $user): Navigation {
            $lockedNavigation = Navigation::query()
                ->whereKey($navigation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->draftResolver->resolve($lockedNavigation);

            if ($this->isIdempotentPublish($lockedNavigation, $draft)) {
                return $lockedNavigation->refresh();
            }

            $this->snapshotValidator->assertPublishable($lockedNavigation, $website, $draft);

            if ($draft->published_at === null) {
                $draft->published_at = now();
                $draft->published_by = $user->id;
                $draft->save();
            }

            $lockedNavigation->assignPublishedVersion($draft);

            return $lockedNavigation->refresh();
        });
    }

    private function isIdempotentPublish(Navigation $navigation, NavigationVersion $draft): bool
    {
        return $navigation->published_version_id !== null
            && (int) $navigation->published_version_id === (int) $draft->id
            && $draft->published_at !== null;
    }
}
