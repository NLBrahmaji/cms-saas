<?php

namespace App\Http\Controllers;

use App\Http\Requests\Website\UpdateWebsiteBrandingRequest;
use App\Http\Resources\Website\WebsiteBrandingResource;
use App\Models\Account;
use App\Models\Website;
use App\Support\Website\WebsiteBrandingUpdater;
use Illuminate\Http\JsonResponse;

class WebsiteBrandingController extends Controller
{
    public function __construct(
        private readonly WebsiteBrandingUpdater $websiteBrandingUpdater,
    ) {}

    public function show(Account $account, Website $website): WebsiteBrandingResource
    {
        $this->authorize('view', $website);

        return new WebsiteBrandingResource($website->branding);
    }

    public function update(
        UpdateWebsiteBrandingRequest $request,
        Account $account,
        Website $website,
    ): JsonResponse {
        $branding = $this->websiteBrandingUpdater->update(
            $website,
            $request->brandingChanges(),
        );

        return (new WebsiteBrandingResource($branding))
            ->response()
            ->setStatusCode(200);
    }
}
