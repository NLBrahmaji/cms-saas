<?php

namespace App\Http\Controllers;

use App\Http\Requests\Navigation\PublishNavigationRequest;
use App\Http\Requests\Navigation\StoreNavigationRequest;
use App\Http\Requests\Navigation\UpdateNavigationRequest;
use App\Http\Resources\Navigation\NavigationResource;
use App\Models\Account;
use App\Models\Navigation;
use App\Models\Website;
use App\Support\Navigation\NavigationCreator;
use App\Support\Navigation\NavigationMetadataUpdater;
use App\Support\Navigation\NavigationPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NavigationController extends Controller
{
    public function __construct(
        private readonly NavigationCreator $navigationCreator,
        private readonly NavigationPublisher $navigationPublisher,
        private readonly NavigationMetadataUpdater $navigationMetadataUpdater,
    ) {}

    public function index(Account $account, Website $website): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Navigation::class);

        $navigations = $website->navigations()
            ->orderBy('id')
            ->get();

        return NavigationResource::collection($navigations);
    }

    public function store(StoreNavigationRequest $request, Account $account, Website $website): JsonResponse
    {
        $navigation = $this->navigationCreator->create(
            $website,
            $request->user(),
            $request->string('name')->toString(),
            $request->string('key')->toString(),
        );

        return (new NavigationResource($navigation))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Account $account, Website $website, Navigation $navigation): NavigationResource
    {
        $this->authorize('view', $navigation);

        return new NavigationResource($navigation);
    }

    public function update(
        UpdateNavigationRequest $request,
        Account $account,
        Website $website,
        Navigation $navigation,
    ): NavigationResource {
        $navigation = $this->navigationMetadataUpdater->update(
            $navigation,
            $request->metadataChanges(),
        );

        return new NavigationResource($navigation);
    }

    public function publish(
        PublishNavigationRequest $request,
        Account $account,
        Website $website,
        Navigation $navigation,
    ): NavigationResource {
        $navigation = $this->navigationPublisher->publish(
            $website,
            $navigation,
            $request->user(),
        );

        return new NavigationResource($navigation);
    }
}
