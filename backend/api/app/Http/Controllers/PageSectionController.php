<?php

namespace App\Http\Controllers;

use App\Http\Requests\Page\StorePageSectionRequest;
use App\Http\Requests\Page\UpdatePageSectionContentRequest;
use App\Http\Resources\Page\PageSectionResource;
use App\Models\Account;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageVersion;
use App\Models\Website;
use App\Support\Page\PageMissingDraftException;
use App\Support\Page\PageSectionContentUpdater;
use App\Support\Page\PageSectionCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PageSectionController extends Controller
{
    public function __construct(
        private readonly PageSectionCreator $pageSectionCreator,
        private readonly PageSectionContentUpdater $pageSectionContentUpdater,
    ) {}

    public function index(Account $account, Website $website, Page $page): AnonymousResourceCollection
    {
        $this->authorize('view', $page);

        $draftVersionId = $page->draft_version_id;

        if ($draftVersionId === null) {
            throw new PageMissingDraftException;
        }

        $draft = PageVersion::query()->find($draftVersionId);

        if ($draft === null || (int) $draft->page_id !== (int) $page->id) {
            throw new PageMissingDraftException;
        }

        $sections = PageSection::query()
            ->where('page_version_id', $draft->id)
            ->with(['template.sectionType', 'content'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return PageSectionResource::collection($sections);
    }

    public function store(
        StorePageSectionRequest $request,
        Account $account,
        Website $website,
        Page $page,
    ): JsonResponse {
        $section = $this->pageSectionCreator->create(
            $page,
            $request->user(),
            $request->integer('section_template_id'),
        );

        return (new PageSectionResource($section))
            ->response()
            ->setStatusCode(201);
    }

    public function updateContent(
        UpdatePageSectionContentRequest $request,
        Account $account,
        Website $website,
        Page $page,
        string $section,
    ): PageSectionResource {
        $updatedSection = $this->pageSectionContentUpdater->update(
            $page,
            $request->user(),
            $section,
            $request->contentDocument(),
        );

        return new PageSectionResource($updatedSection);
    }
}
