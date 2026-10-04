<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class PageSoftDeleter
{
    public function delete(Website $website, Page $page): void
    {
        DB::transaction(function () use ($website, $page): void {
            $lockedWebsite = Website::query()
                ->whereKey($website->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPage = Page::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedWebsite->home_page_id === (int) $lockedPage->id) {
                $lockedWebsite->assignHomePage(null);
            }

            $lockedPage->delete();
        });
    }
}
