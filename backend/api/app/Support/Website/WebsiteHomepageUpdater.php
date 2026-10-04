<?php

namespace App\Support\Website;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WebsiteHomepageUpdater
{
    public function update(Website $website, ?int $pageId): Website
    {
        return DB::transaction(function () use ($website, $pageId): Website {
            $lockedWebsite = Website::query()
                ->whereKey($website->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pageId === null) {
                if ($lockedWebsite->home_page_id === null) {
                    return $lockedWebsite;
                }

                $lockedWebsite->assignHomePage(null);

                return $lockedWebsite->refresh();
            }

            $page = Page::query()->find($pageId);

            if ($page === null) {
                throw ValidationException::withMessages([
                    'page_id' => ['The selected page is invalid.'],
                ]);
            }

            if ($page->trashed()) {
                throw ValidationException::withMessages([
                    'page_id' => ['The selected page is invalid.'],
                ]);
            }

            if ((int) $page->website_id !== (int) $lockedWebsite->id) {
                throw ValidationException::withMessages([
                    'page_id' => ['The selected page is invalid.'],
                ]);
            }

            if ((int) $lockedWebsite->home_page_id === (int) $page->id) {
                return $lockedWebsite;
            }

            $lockedWebsite->assignHomePage($page);

            return $lockedWebsite->refresh();
        });
    }
}
