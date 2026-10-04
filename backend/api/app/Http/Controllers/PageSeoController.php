<?php

namespace App\Http\Controllers;

use App\Http\Requests\Page\UpdatePageSeoRequest;
use App\Http\Resources\Page\PageSeoResource;
use App\Models\Account;
use App\Models\Page;
use App\Models\Website;
use App\Support\Page\DraftPageSeoResolver;
use App\Support\Page\PageSeoUpdater;

class PageSeoController extends Controller
{
    public function __construct(
        private readonly DraftPageSeoResolver $draftPageSeoResolver,
        private readonly PageSeoUpdater $pageSeoUpdater,
    ) {}

    public function show(Account $account, Website $website, Page $page): PageSeoResource
    {
        $this->authorize('view', $page);

        $state = $this->draftPageSeoResolver->resolveForPage($page);

        return new PageSeoResource($state);
    }

    public function update(
        UpdatePageSeoRequest $request,
        Account $account,
        Website $website,
        Page $page,
    ): PageSeoResource {
        $state = $this->pageSeoUpdater->update(
            $page,
            $request->user(),
            $request->seoChanges(),
        );

        return new PageSeoResource($state);
    }
}
