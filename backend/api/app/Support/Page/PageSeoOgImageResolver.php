<?php

namespace App\Support\Page;

use App\Models\Website;
use App\Support\Media\WebsiteSelectableMedia;

class PageSeoOgImageResolver
{
    public function __construct(
        private readonly WebsiteSelectableMedia $websiteSelectableMedia,
    ) {}

    public function isSelectableOgImage(Website $website, int $mediaId): bool
    {
        return $this->websiteSelectableMedia->isSelectableRasterImage($website, $mediaId);
    }
}
