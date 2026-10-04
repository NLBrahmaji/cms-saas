<?php

namespace App\Http\Controllers;

use App\Http\Requests\Page\StorePageRequest;
use App\Http\Resources\Page\PageResource;
use App\Models\Account;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\Website;
use App\Support\Page\PageSlugAllocator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    public function __construct(
        private readonly PageSlugAllocator $slugAllocator,
    ) {}

    public function index(Account $account, Website $website): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Page::class);

        $pages = $website->pages()
            ->with('draftVersion')
            ->orderBy('id')
            ->get();

        return PageResource::collection($pages);
    }

    public function store(StorePageRequest $request, Account $account, Website $website): JsonResponse
    {
        $name = $request->string('name')->toString();

        $page = $this->createPage($website, $request->user(), $name);

        return (new PageResource($page->load('draftVersion')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Account $account, Website $website, Page $page): PageResource
    {
        $this->authorize('view', $page);

        $page->loadMissing('draftVersion');

        return new PageResource($page);
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
                'is_home' => false,
                'parent_page_id' => null,
                'created_by' => $user->id,
                'published_by' => null,
                'published_at' => null,
            ]);

            $page->assignDraftVersion($version);

            return $page->refresh();
        });
    }
}
