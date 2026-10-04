<?php

namespace App\Http\Controllers;

use App\Http\Requests\Media\StoreMediaRequest;
use App\Http\Requests\Media\UpdateMediaRequest;
use App\Http\Resources\Media\MediaResource;
use App\Models\Account;
use App\Models\Media;
use App\Models\Website;
use App\Support\Media\MediaMetadataUpdater;
use App\Support\Media\MediaUploader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaUploader $mediaUploader,
        private readonly MediaMetadataUpdater $mediaMetadataUpdater,
    ) {}

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

    public function store(StoreMediaRequest $request, Account $account, Website $website): JsonResponse
    {
        $media = $this->mediaUploader->upload(
            $website,
            $request->user(),
            $request->file('file'),
        );

        return (new MediaResource($media))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateMediaRequest $request,
        Account $account,
        Website $website,
        Media $media,
    ): MediaResource {
        $media = $this->mediaMetadataUpdater->update(
            $media,
            $request->metadataChanges(),
        );

        return new MediaResource($media);
    }
}
