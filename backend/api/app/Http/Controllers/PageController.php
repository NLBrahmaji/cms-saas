<?php

namespace App\Http\Controllers;

use App\Http\Requests\Page\PublishPageRequest;
use App\Http\Requests\Page\StorePageRequest;
use App\Http\Requests\Page\UpdatePageRequest;
use App\Http\Resources\Page\PageResource;
use App\Models\Account;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\Website;
use App\Support\Page\PageDraftMetadataUpdater;
use App\Support\Page\PagePublisher;
use App\Support\Page\PageSlugAllocator;
use App\Support\Page\PageSoftDeleter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    public function __construct(
        private readonly PageSlugAllocator $slugAllocator,
        private readonly PageDraftMetadataUpdater $draftMetadataUpdater,
        private readonly PagePublisher $pagePublisher,
        private readonly PageSoftDeleter $pageSoftDeleter,
    ) {}

    public function index(Account $account, Website $website): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Page::class);

        $pages = $website->pages()
            ->with('draftVersion')
            ->orderBy('id')
            ->get();

        $this->attachWebsiteContext($website, $pages);

        return PageResource::collection($pages);
    }

    public function store(StorePageRequest $request, Account $account, Website $website): JsonResponse
    {
        $name = $request->string('name')->toString();

        $page = $this->createPage($website, $request->user(), $name);

        return (new PageResource($this->attachWebsiteContext($website, $page->load('draftVersion'))))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Account $account, Website $website, Page $page): PageResource
    {
        $this->authorize('view', $page);

        return new PageResource($this->attachWebsiteContext($website, $page->loadMissing('draftVersion')));
    }

    public function update(UpdatePageRequest $request, Account $account, Website $website, Page $page): PageResource
    {
        $changes = [];

        if ($request->has('name')) {
            $changes['name'] = $request->string('name')->toString();
        }

        if ($request->has('slug')) {
            $changes['slug'] = $request->string('slug')->toString();
        }

        $page = $this->draftMetadataUpdater->update($page, $request->user(), $changes);

        return new PageResource($this->attachWebsiteContext($website, $page));
    }

    public function destroy(Account $account, Website $website, Page $page): Response
    {
        $this->authorize('delete', $page);

        $this->pageSoftDeleter->delete($website, $page);

        return response()->noContent();
    }

    public function publish(PublishPageRequest $request, Account $account, Website $website, Page $page): PageResource
    {
        $page = $this->pagePublisher->publish($website, $page, $request->user());

        return new PageResource($this->attachWebsiteContext($website, $page));
    }

    private function createPage(Website $website, User $user, string $name): Page
    {
        $slug = $this->slugAllocator->allocate($website, $name);

        return DB::transaction(function () use ($website, $user, $name, $slug): Page {
            $page = Page::query()->create([
                'website_id' => $website->id,
            ]);

            $version = PageVersion::query()->create([
                'page_id' => $page->id,
                'version' => 1,
                'name' => $name,
                'slug' => $slug,
                'parent_page_id' => null,
                'created_by' => $user->id,
                'published_by' => null,
                'published_at' => null,
            ]);

            $page->assignDraftVersion($version);

            return $page->refresh();
        });
    }

    /**
     * @param  Collection<int, Page>|Page  $pages
     */
    private function attachWebsiteContext(Website $website, Collection|Page $pages): Collection|Page
    {
        if ($pages instanceof Page) {
            $pages->setRelation('website', $website);

            return $pages;
        }

        $pages->each(function (Page $page) use ($website): void {
            $page->setRelation('website', $website);
        });

        return $pages;
    }
}
