<?php

namespace App\Http\Controllers;

use App\Http\Requests\Website\UpdateWebsiteSeoRequest;
use App\Http\Resources\Website\WebsiteSeoResource;
use App\Models\Account;
use App\Models\Website;
use App\Support\Website\WebsiteSeoUpdater;
use Illuminate\Http\JsonResponse;

class WebsiteSeoController extends Controller
{
    public function __construct(
        private readonly WebsiteSeoUpdater $websiteSeoUpdater,
    ) {}

    public function show(Account $account, Website $website): WebsiteSeoResource
    {
        $this->authorize('view', $website);

        return new WebsiteSeoResource($website->seoSetting);
    }

    public function update(
        UpdateWebsiteSeoRequest $request,
        Account $account,
        Website $website,
    ): JsonResponse {
        $seo = $this->websiteSeoUpdater->update(
            $website,
            $request->seoChanges(),
        );

        return (new WebsiteSeoResource($seo))
            ->response()
            ->setStatusCode(200);
    }
}
