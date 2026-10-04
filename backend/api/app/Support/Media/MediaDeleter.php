<?php

namespace App\Support\Media;

use App\Models\Media;
use Illuminate\Support\Facades\DB;

class MediaDeleter
{
    public function delete(Media $media): void
    {
        DB::transaction(function () use ($media): void {
            $lockedMedia = Media::query()
                ->whereKey($media->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMedia->delete();
        });
    }
}
