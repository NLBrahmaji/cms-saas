<?php

namespace App\Support\Page;

use App\Models\Website;
use App\Support\Media\MediaRasterImage;

class PageSeoOgImageResolver
{
    public function isSelectableOgImage(Website $website, int $mediaId): bool
    {
        $media = $website->media()
            ->whereKey($mediaId)
            ->first();

        if ($media === null) {
            return false;
        }

        return MediaRasterImage::isAllowedMimeType($media->mime_type);
    }
}
