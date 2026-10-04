<?php

namespace App\Http\Controllers;

use App\Http\Requests\Website\StoreWebsiteRequest;
use App\Http\Resources\Website\WebsiteResource;
use App\Models\Account;
use App\Models\Website;
use App\Models\WebsiteSetting;
use App\Support\Website\WebsiteSubdomainAllocator;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class WebsiteController extends Controller
{
    public function __construct(
        private readonly WebsiteSubdomainAllocator $subdomainAllocator,
    ) {}

    public function index(Account $account): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Website::class);

        $websites = $account->websites()
            ->orderBy('id')
            ->get();

        return WebsiteResource::collection($websites);
    }

    public function store(StoreWebsiteRequest $request, Account $account): JsonResponse
    {
        $name = $request->string('name')->toString();

        $website = $this->createWebsiteWithSettings($account, $name);

        return (new WebsiteResource($website))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Account $account, Website $website): WebsiteResource
    {
        $this->authorize('view', $website);

        return new WebsiteResource($website);
    }

    private function createWebsiteWithSettings(Account $account, string $name): Website
    {
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->attemptCreateWebsiteWithSettings($account, $name);
            } catch (QueryException $exception) {
                if (! $this->isWebsiteSubdomainUniqueViolation($exception) || $attempt === $maxAttempts) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to allocate a unique website subdomain.');
    }

    private function attemptCreateWebsiteWithSettings(Account $account, string $name): Website
    {
        $subdomain = $this->subdomainAllocator->allocate($name);

        return DB::transaction(function () use ($account, $name, $subdomain): Website {
            $website = Website::query()->create([
                'account_id' => $account->id,
                'name' => $name,
                'subdomain' => $subdomain,
                'status' => Website::STATUS_DRAFT,
                'timezone' => 'UTC',
                'published_at' => null,
            ]);

            WebsiteSetting::query()->create([
                'website_id' => $website->id,
                'site_name' => $website->name,
            ]);

            return $website;
        });
    }

    private function isWebsiteSubdomainUniqueViolation(QueryException $exception): bool
    {
        $errorCode = (string) $exception->getCode();

        if (in_array($errorCode, ['23000', '23505'], true)) {
            return str_contains(strtolower($exception->getMessage()), 'subdomain');
        }

        return false;
    }
}
