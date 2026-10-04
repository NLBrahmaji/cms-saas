<?php

namespace App\Support\Media;

use App\Models\Media;
use Illuminate\Support\Facades\DB;

class MediaMetadataUpdater
{
    /**
     * @param  array<string, string|null>  $changes
     */
    public function update(Media $media, array $changes): Media
    {
        if ($changes === []) {
            return $media;
        }

        return DB::transaction(function () use ($media, $changes): Media {
            $lockedMedia = Media::query()
                ->whereKey($media->id)
                ->lockForUpdate()
                ->firstOrFail();

            $payload = [];

            if (array_key_exists('alt_text', $changes) && $changes['alt_text'] !== $lockedMedia->alt_text) {
                $payload['alt_text'] = $changes['alt_text'];
            }

            if (array_key_exists('title', $changes) && $changes['title'] !== $lockedMedia->title) {
                $payload['title'] = $changes['title'];
            }

            if ($payload === []) {
                return $lockedMedia;
            }

            $lockedMedia->update($payload);

            return $lockedMedia->refresh();
        });
    }
}
