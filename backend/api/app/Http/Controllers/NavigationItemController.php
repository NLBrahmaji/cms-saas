<?php

namespace App\Http\Controllers;

use App\Http\Requests\Navigation\StoreNavigationItemRequest;
use App\Http\Requests\Navigation\UpdateNavigationItemRequest;
use App\Http\Resources\Navigation\NavigationItemResource;
use App\Models\Account;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\Website;
use App\Support\Navigation\DraftNavigationVersionResolver;
use App\Support\Navigation\NavigationItemCreator;
use App\Support\Navigation\NavigationItemUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NavigationItemController extends Controller
{
    public function __construct(
        private readonly DraftNavigationVersionResolver $draftResolver,
        private readonly NavigationItemCreator $navigationItemCreator,
        private readonly NavigationItemUpdater $navigationItemUpdater,
    ) {}

    public function index(Account $account, Website $website, Navigation $navigation): AnonymousResourceCollection
    {
        $this->authorize('view', $navigation);

        $draft = $this->draftResolver->resolve($navigation);

        $items = NavigationItem::query()
            ->where('navigation_version_id', $draft->id)
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return NavigationItemResource::collection($items);
    }

    public function store(
        StoreNavigationItemRequest $request,
        Account $account,
        Website $website,
        Navigation $navigation,
    ): JsonResponse {
        $item = $this->navigationItemCreator->create(
            $navigation,
            $request->user(),
            $request->itemPayload(),
        );

        return (new NavigationItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateNavigationItemRequest $request,
        Account $account,
        Website $website,
        Navigation $navigation,
        string $item,
    ): NavigationItemResource {
        $updated = $this->navigationItemUpdater->update(
            $navigation,
            $website,
            $request->user(),
            $item,
            $request->itemChanges(),
        );

        return new NavigationItemResource($updated);
    }
}
