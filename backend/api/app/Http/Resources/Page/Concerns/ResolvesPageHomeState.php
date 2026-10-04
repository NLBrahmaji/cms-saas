<?php

namespace App\Http\Resources\Page\Concerns;

use App\Models\Page;
use App\Models\Website;

trait ResolvesPageHomeState
{
    protected function pageIsHome(Page $page): bool
    {
        $website = $page->relationLoaded('website')
            ? $page->getRelation('website')
            : $page->website;

        if (! $website instanceof Website) {
            return false;
        }

        return $website->home_page_id !== null
            && (int) $website->home_page_id === (int) $page->id;
    }
}
