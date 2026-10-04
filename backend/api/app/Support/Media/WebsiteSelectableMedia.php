<?php

namespace App\Support\Media;

use App\Models\Website;

class WebsiteSelectableMedia
{
    public function isSelectableRasterImage(Website $website, int $mediaId): bool
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
