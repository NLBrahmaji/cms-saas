<?php

namespace App\Http\Controllers;

use App\Http\Resources\Media\MediaResource;
use App\Models\Account;
use App\Models\Media;
use App\Models\Website;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaController extends Controller
{
    public function index(Account $account, Website $website): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Media::class);

        $media = $website->media()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return MediaResource::collection($media);
    }

    public function show(Account $account, Website $website, Media $media): MediaResource
    {
        $this->authorize('view', $media);

        return new MediaResource($media);
    }
}
