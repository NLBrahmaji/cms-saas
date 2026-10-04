<?php

namespace App\Http\Controllers;

use App\Http\Requests\Navigation\StoreNavigationRequest;
use App\Http\Resources\Navigation\NavigationResource;
use App\Models\Account;
use App\Models\Navigation;
use App\Models\Website;
use App\Support\Navigation\NavigationCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NavigationController extends Controller
{
    public function __construct(
        private readonly NavigationCreator $navigationCreator,
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
}
