<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\PageVersionSeoSetting;
use App\Models\User;
use App\Models\Website;
use App\Support\Page\PageVersionSnapshotCloner;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function pageSeoOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageSeoCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetPageSeo(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSeoOriginHeaders())->getJson($uri);

    syncPageSeoCookies($response);

    return $response;
}

function statefulPatchPageSeo(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageSeoOriginHeaders())->patchJson($uri, $data);

    syncPageSeoCookies($response);

    return $response;
}

function statefulPatchPageSeoRaw(string $uri, string $json): TestResponse
{
    $response = test()->call(
        'PATCH',
        $uri,
        [],
        [],
        [],
        [
            'HTTP_ORIGIN' => 'http://localhost:3001',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ],
        $json,
    );

    syncPageSeoCookies($response);

    return $response;
}

function statefulPostPageSeoPublish(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSeoOriginHeaders())->postJson($uri);

    syncPageSeoCookies($response);

    return $response;
}

function loginPageSeoUser(User $user): void
{
    test()->withHeaders(pageSeoOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createPageSeoUser(): User
{
    return User::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada-page-seo-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachPageSeoMembership(User $user, array $permissions = AccountPermissionSeeder::PERMISSIONS): Account
{
    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => 'Ada Account',
        'status' => 'active',
    ]);

    AccountMember::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $role = Role::query()->create([
        'name' => 'owner',
        'guard_name' => AccountPermissionSeeder::GUARD,
        'team_id' => $account->id,
    ]);

    $role->givePermissionTo($permissions);

    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => User::class,
        'model_id' => $user->id,
        'team_id' => $account->id,
    ]);

    return $account;
}

function createPageSeoWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageSeoUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/seo';
}

function pageSeoPublishUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/publish';
}

function createPageSeoPage(Website $website, User $creator): Page
{
    $page = Page::query()->create(['website_id' => $website->id]);

    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'About',
        'slug' => 'about',
        'created_by' => $creator->id,
    ]);

    $page->assignDraftVersion($version);

    return $page->refresh();
}

function createPageSeoMedia(Website $website, User $user, string $mimeType = 'image/jpeg'): int
{
    return (int) DB::table('media')->insertGetId([
        'website_id' => $website->id,
        'disk' => 'public',
        'path' => 'images/hero-'.uniqid().'.jpg',
        'original_name' => 'hero.jpg',
        'mime_type' => $mimeType,
        'extension' => 'jpg',
        'size' => 1024,
        'source' => 'upload',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function softDeletePageSeoMedia(int $mediaId): void
{
    Media::query()->whereKey($mediaId)->delete();
}

function createPageSeoRow(PageVersion $version, array $overrides = []): PageVersionSeoSetting
{
    return PageVersionSeoSetting::query()->create(array_merge([
        'page_version_id' => $version->id,
        'meta_title' => 'Stored title',
        'meta_description' => 'Stored description',
        'og_title' => 'OG title',
        'og_description' => 'OG description',
        'canonical_url' => 'https://example.com/old',
        'robots_index' => true,
        'robots_follow' => false,
    ], $overrides));
}

function simulatePageSeoPublished(Page $page, User $publisher): void
{
    $page->refresh();
    $version = $page->draftVersion;

    $version->update([
        'published_at' => now(),
        'published_by' => $publisher->id,
    ]);

    $page->update([
        'published_version_id' => $version->id,
    ]);
}

function expectPageSeoNullShape(TestResponse $response): void
{
    $response
        ->assertJsonPath('data.meta_title', null)
        ->assertJsonPath('data.meta_description', null)
        ->assertJsonPath('data.og_title', null)
        ->assertJsonPath('data.og_description', null)
        ->assertJsonPath('data.og_image_id', null)
        ->assertJsonPath('data.canonical_url', null)
        ->assertJsonPath('data.robots_index', null)
        ->assertJsonPath('data.robots_follow', null);
}

test('unauthenticated page seo get is unauthorized', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);

    test()->getJson(pageSeoUri($account, $website, $page))->assertUnauthorized();
});

test('page view permission allows seo get', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user, ['page.view']);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);

    loginPageSeoUser($user);

    statefulGetPageSeo(pageSeoUri($account, $website, $page))
        ->assertOk();

    expectPageSeoNullShape(statefulGetPageSeo(pageSeoUri($account, $website, $page)));
});

test('page seo get returns null shape without creating row', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);

    loginPageSeoUser($user);

    statefulGetPageSeo(pageSeoUri($account, $website, $page))->assertOk();

    expectPageSeoNullShape(statefulGetPageSeo(pageSeoUri($account, $website, $page)));
    expect(PageVersionSeoSetting::query()->count())->toBe(0);
});

test('page seo get returns stored draft values including og image id', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $mediaId = createPageSeoMedia($website, $user);

    createPageSeoRow($page->draftVersion, ['og_image_id' => $mediaId]);

    loginPageSeoUser($user);

    statefulGetPageSeo(pageSeoUri($account, $website, $page))
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Stored title')
        ->assertJsonPath('data.og_image_id', $mediaId)
        ->assertJsonPath('data.robots_follow', false);
});

test('page seo get reads ahead draft not published version', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;

    createPageSeoRow($versionOne, ['meta_title' => 'Published SEO']);
    simulatePageSeoPublished($page, $user);

    $versionTwo = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionOne, $user);
    $page->assignDraftVersion($versionTwo);

    PageVersionSeoSetting::query()
        ->where('page_version_id', $versionTwo->id)
        ->firstOrFail()
        ->update(['meta_title' => 'Draft SEO']);

    loginPageSeoUser($user);

    statefulGetPageSeo(pageSeoUri($account, $website, $page))
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Draft SEO');
});

test('page seo get returns not found for wrong tenant nesting', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $otherUser = createPageSeoUser();
    $otherAccount = attachPageSeoMembership($otherUser);
    $website = createPageSeoWebsite($account);
    $otherWebsite = createPageSeoWebsite($otherAccount);
    $page = createPageSeoPage($website, $user);

    loginPageSeoUser($user);

    statefulGetPageSeo('/v1/accounts/'.$otherAccount->id.'/websites/'.$website->id.'/pages/'.$page->id.'/seo')
        ->assertNotFound();

    statefulGetPageSeo('/v1/accounts/'.$account->id.'/websites/'.$otherWebsite->id.'/pages/'.$page->id.'/seo')
        ->assertNotFound();
});

test('page seo get returns not found for soft deleted page', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);

    loginPageSeoUser($user);

    $page->delete();

    statefulGetPageSeo(pageSeoUri($account, $website, $page))->assertNotFound();
});

test('page seo get fails when draft pointer is corrupt', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);

    $page->update(['draft_version_id' => null]);

    loginPageSeoUser($user);

    statefulGetPageSeo(pageSeoUri($account, $website, $page))->assertStatus(500);
});

test('unauthenticated page seo patch is unauthorized', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);

    test()->patchJson(pageSeoUri($account, $website, $page), ['meta_title' => 'Nope'])
        ->assertUnauthorized();
});

test('page update permission is required to patch seo', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user, ['page.view']);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'Nope'])
        ->assertForbidden();
});

test('page seo patch validates request shape', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $uri = pageSeoUri($account, $website, $page);

    loginPageSeoUser($user);

    statefulPatchPageSeo($uri, [])->assertUnprocessable();
    statefulPatchPageSeo($uri, ['unexpected' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors(['unexpected']);
    statefulPatchPageSeo($uri, ['og_image_id' => 1])->assertUnprocessable()->assertJsonValidationErrors(['og_image_id']);

    statefulPatchPageSeo($uri, ['meta_title' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['meta_title']);

    statefulPatchPageSeo($uri, ['canonical_url' => '/about'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['canonical_url']);

    statefulPatchPageSeo($uri, ['canonical_url' => 'ftp://example.com/about'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['canonical_url']);

    statefulPatchPageSeoRaw($uri, '{"robots_index":"false"}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['robots_index']);

    statefulPatchPageSeoRaw($uri, '{"robots_index":0}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['robots_index']);
});

test('page seo patch accepts valid canonical and robots values', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $uri = pageSeoUri($account, $website, $page);

    loginPageSeoUser($user);

    statefulPatchPageSeo($uri, [
        'meta_title' => 'About',
        'meta_description' => 'Long description text',
        'canonical_url' => 'https://example.com/about',
    ])->assertOk();

    statefulPatchPageSeoRaw($uri, '{"robots_index":true,"robots_follow":false}')
        ->assertOk()
        ->assertJsonPath('data.robots_index', true)
        ->assertJsonPath('data.robots_follow', false);

    statefulPatchPageSeoRaw($uri, '{"robots_index":null}')
        ->assertOk()
        ->assertJsonPath('data.robots_index', null);
});

test('unpublished page seo patch creates row on version one', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'Welcome'])
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Welcome');

    expect(PageVersion::query()->count())->toBe(1)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $page->draft_version_id)->count())->toBe(1);
});

test('published missing seo row all null patch is no op', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;

    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeoRaw(pageSeoUri($account, $website, $page), '{"meta_title":null}')
        ->assertOk();

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($versionOne->id)
        ->and(PageVersionSeoSetting::query()->count())->toBe(0);
});

test('published missing seo row meaningful patch clones and creates row on v2 only', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;

    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'Welcome'])
        ->assertOk();

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect($page->published_version_id)->toBe($versionOne->id)
        ->and($page->draft_version_id)->toBe($versionTwo->id)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->count())->toBe(0)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionTwo->id)->count())->toBe(1);
});

test('published existing seo identical patch is no op', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;
    $seo = createPageSeoRow($versionOne, [
        'meta_title' => 'Welcome',
        'robots_index' => false,
    ]);

    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    $updatedAt = $seo->updated_at;

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'Welcome'])
        ->assertOk();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($seo->refresh()->updated_at->eq($updatedAt))->toBeTrue();
});

test('published existing seo partial patch clones and preserves omitted fields', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;
    $mediaId = createPageSeoMedia($website, $user);

    createPageSeoRow($versionOne, [
        'meta_title' => 'Old title',
        'meta_description' => 'Keep me',
        'og_image_id' => $mediaId,
        'robots_index' => true,
    ]);

    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'New title'])
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'New title')
        ->assertJsonPath('data.meta_description', 'Keep me')
        ->assertJsonPath('data.og_image_id', $mediaId)
        ->assertJsonPath('data.robots_index', true);

    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();
    $v1Seo = PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->firstOrFail();
    $v2Seo = PageVersionSeoSetting::query()->where('page_version_id', $versionTwo->id)->firstOrFail();

    expect($v1Seo->meta_title)->toBe('Old title')
        ->and($v2Seo->meta_title)->toBe('New title')
        ->and($v2Seo->meta_description)->toBe('Keep me')
        ->and($v2Seo->og_image_id)->toBe($mediaId);
});

test('page seo patch preserves omitted fields on unpublished draft', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $seo = createPageSeoRow($page->draftVersion, [
        'meta_title' => 'Old title',
        'meta_description' => 'Keep me',
        'robots_index' => true,
    ]);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'New title'])
        ->assertOk();

    $seo->refresh();

    expect($seo->meta_title)->toBe('New title')
        ->and($seo->meta_description)->toBe('Keep me')
        ->and($seo->robots_index)->toBeTrue();
});

test('page seo patch clears nullable fields explicitly', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    createPageSeoRow($page->draftVersion, ['canonical_url' => 'https://example.com/old']);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['canonical_url' => null])
        ->assertOk()
        ->assertJsonPath('data.canonical_url', null);
});

test('ahead draft seo patch does not create version three', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;

    createPageSeoRow($versionOne, ['meta_title' => 'Published']);
    simulatePageSeoPublished($page, $user);

    $versionTwo = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionOne, $user);
    $page->assignDraftVersion($versionTwo);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'Draft edit'])
        ->assertOk();

    expect(PageVersion::query()->count())->toBe(2)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->first()?->meta_title)
        ->toBe('Published');
});

test('page seo patch rolls back clone when persistence fails', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;
    $seo = createPageSeoRow($versionOne, ['meta_title' => 'Published']);

    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    PageVersionSeoSetting::updating(function (): void {
        throw new RuntimeException('Simulated page seo persistence failure');
    });

    try {
        statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'Changed'])
            ->assertStatus(500);
    } finally {
        PageVersionSeoSetting::flushEventListeners();
    }

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($versionOne->id)
        ->and($seo->refresh()->meta_title)->toBe('Published');
});

test('page seo publish integration promotes draft seo snapshot', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;

    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'Draft SEO'])
        ->assertOk();

    statefulPostPageSeoPublish(pageSeoPublishUri($account, $website, $page))->assertOk();

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect($page->draft_version_id)->toBe($versionTwo->id)
        ->and($page->published_version_id)->toBe($versionTwo->id)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->count())->toBe(0)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionTwo->id)->first()?->meta_title)
        ->toBe('Draft SEO');

    statefulGetPageSeo(pageSeoUri($account, $website, $page))
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'Draft SEO');
});

test('page seo patch returns not found for wrong tenant context', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $otherUser = createPageSeoUser();
    $otherAccount = attachPageSeoMembership($otherUser);
    $website = createPageSeoWebsite($account);
    $otherWebsite = createPageSeoWebsite($otherAccount);
    $page = createPageSeoPage($website, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo('/v1/accounts/'.$otherAccount->id.'/websites/'.$website->id.'/pages/'.$page->id.'/seo', [
        'meta_title' => 'Nope',
    ])->assertNotFound();

    statefulPatchPageSeo('/v1/accounts/'.$account->id.'/websites/'.$otherWebsite->id.'/pages/'.$page->id.'/seo', [
        'meta_title' => 'Nope',
    ])->assertNotFound();
});

test('page seo patch accepts active same website raster og image mime types', function (string $mimeType) {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $mediaId = createPageSeoMedia($website, $user, $mimeType);
    $uri = pageSeoUri($account, $website, $page);

    loginPageSeoUser($user);

    statefulPatchPageSeo($uri, ['og_image_id' => $mediaId])
        ->assertOk()
        ->assertJsonPath('data.og_image_id', $mediaId);
})->with([
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'webp' => ['image/webp'],
    'gif' => ['image/gif'],
]);

test('page seo patch rejects invalid og image targets', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $otherWebsite = createPageSeoWebsite($account);
    $otherUser = createPageSeoUser();
    $otherAccount = attachPageSeoMembership($otherUser);
    $foreignWebsite = createPageSeoWebsite($otherAccount);
    $page = createPageSeoPage($website, $user);
    $uri = pageSeoUri($account, $website, $page);

    $sameAccountOtherSite = createPageSeoMedia($otherWebsite, $user);
    $foreignMedia = createPageSeoMedia($foreignWebsite, $otherUser);
    $deletedMedia = createPageSeoMedia($website, $user);
    softDeletePageSeoMedia($deletedMedia);
    $unsupportedMime = createPageSeoMedia($website, $user, 'application/pdf');

    loginPageSeoUser($user);

    statefulPatchPageSeo($uri, ['og_image_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['og_image_id']);

    statefulPatchPageSeo($uri, ['og_image_id' => $sameAccountOtherSite])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['og_image_id']);

    statefulPatchPageSeo($uri, ['og_image_id' => $foreignMedia])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['og_image_id']);

    statefulPatchPageSeo($uri, ['og_image_id' => $deletedMedia])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['og_image_id']);

    statefulPatchPageSeo($uri, ['og_image_id' => $unsupportedMime])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['og_image_id']);

    statefulPatchPageSeoRaw($uri, '{"og_image_id":"1"}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['og_image_id']);

    statefulPatchPageSeoRaw($uri, '{"og_image_id":{"id":1}}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['og_image_id']);
});

test('page seo patch og image null on missing row is semantic no op', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draft_version_id;

    loginPageSeoUser($user);

    statefulPatchPageSeoRaw(pageSeoUri($account, $website, $page), '{"og_image_id":null}')
        ->assertOk();

    $page->refresh();

    expect(PageVersionSeoSetting::query()->count())->toBe(0)
        ->and($page->draft_version_id)->toBe($versionOne)
        ->and(PageVersion::query()->count())->toBe(1);
});

test('page seo patch same active og image id is semantic no op on canonical published page', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;
    $mediaId = createPageSeoMedia($website, $user);
    $seo = createPageSeoRow($versionOne, ['og_image_id' => $mediaId]);

    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    $updatedAt = $seo->updated_at;

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['og_image_id' => $mediaId])
        ->assertOk()
        ->assertJsonPath('data.og_image_id', $mediaId);

    expect(PageVersion::query()->count())->toBe(1)
        ->and($seo->refresh()->updated_at->eq($updatedAt))->toBeTrue();
});

test('page seo patch explicit stale og image id after media soft delete is not no op', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $mediaId = createPageSeoMedia($website, $user);

    createPageSeoRow($page->draftVersion, ['og_image_id' => $mediaId]);
    softDeletePageSeoMedia($mediaId);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['og_image_id' => $mediaId])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['og_image_id']);
});

test('unpublished page seo og image patch mutates version one', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $mediaId = createPageSeoMedia($website, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['og_image_id' => $mediaId])
        ->assertOk();

    expect(PageVersion::query()->count())->toBe(1)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $page->draft_version_id)->value('og_image_id'))
        ->toBe($mediaId);
});

test('published canonical page og image change clones to version two', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;
    $imageA = createPageSeoMedia($website, $user);
    $imageB = createPageSeoMedia($website, $user);

    createPageSeoRow($versionOne, ['og_image_id' => $imageA, 'meta_title' => 'Published']);
    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['og_image_id' => $imageB])
        ->assertOk();

    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();
    $v1Seo = PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->firstOrFail();
    $v2Seo = PageVersionSeoSetting::query()->where('page_version_id', $versionTwo->id)->firstOrFail();

    expect($v1Seo->og_image_id)->toBe($imageA)
        ->and($v2Seo->og_image_id)->toBe($imageB)
        ->and($v1Seo->meta_title)->toBe('Published');
});

test('ahead draft og image patch mutates version two without creating version three', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;
    $imageA = createPageSeoMedia($website, $user);
    $imageB = createPageSeoMedia($website, $user);
    $imageC = createPageSeoMedia($website, $user);

    createPageSeoRow($versionOne, ['og_image_id' => $imageA]);
    simulatePageSeoPublished($page, $user);

    $versionTwo = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionOne, $user);
    $page->assignDraftVersion($versionTwo);
    PageVersionSeoSetting::query()->where('page_version_id', $versionTwo->id)->firstOrFail()->update(['og_image_id' => $imageB]);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['og_image_id' => $imageC])
        ->assertOk();

    expect(PageVersion::query()->count())->toBe(2)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->first()?->og_image_id)->toBe($imageA)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionTwo->id)->first()?->og_image_id)->toBe($imageC);
});

test('page seo patch stale historical og image id allows unrelated field updates', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $mediaId = createPageSeoMedia($website, $user);
    $seo = createPageSeoRow($page->draftVersion, [
        'og_image_id' => $mediaId,
        'meta_title' => 'Old',
    ]);

    softDeletePageSeoMedia($mediaId);

    loginPageSeoUser($user);

    statefulGetPageSeo(pageSeoUri($account, $website, $page))
        ->assertOk()
        ->assertJsonPath('data.og_image_id', $mediaId);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['meta_title' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.meta_title', 'New')
        ->assertJsonPath('data.og_image_id', $mediaId);

    expect($seo->refresh()->og_image_id)->toBe($mediaId);
});

test('page seo combined patch with invalid og image rejects before mutation', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;
    createPageSeoRow($versionOne, ['meta_title' => 'Published']);
    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), [
        'meta_title' => 'Changed',
        'og_image_id' => 999999,
    ])->assertUnprocessable();

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->first()?->meta_title)->toBe('Published');
});

test('page seo combined patch with valid og image updates once', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;
    $mediaId = createPageSeoMedia($website, $user);

    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), [
        'meta_title' => 'Draft SEO',
        'og_image_id' => $mediaId,
    ])->assertOk();

    expect(PageVersion::query()->count())->toBe(2)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->count())->toBe(0);

    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();
    $v2Seo = PageVersionSeoSetting::query()->where('page_version_id', $versionTwo->id)->firstOrFail();

    expect($v2Seo->meta_title)->toBe('Draft SEO')
        ->and($v2Seo->og_image_id)->toBe($mediaId);
});

test('page seo publish promotes draft og image snapshot', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $versionOne = $page->draftVersion;
    $imagePublished = createPageSeoMedia($website, $user);
    $imageDraft = createPageSeoMedia($website, $user);

    createPageSeoRow($versionOne, ['og_image_id' => $imagePublished]);
    simulatePageSeoPublished($page, $user);

    loginPageSeoUser($user);

    statefulPatchPageSeo(pageSeoUri($account, $website, $page), ['og_image_id' => $imageDraft])
        ->assertOk();

    statefulPostPageSeoPublish(pageSeoPublishUri($account, $website, $page))->assertOk();

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect($page->published_version_id)->toBe($versionTwo->id)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->first()?->og_image_id)->toBe($imagePublished)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionTwo->id)->first()?->og_image_id)->toBe($imageDraft);
});

test('page seo patch clears og image id explicitly', function () {
    $user = createPageSeoUser();
    $account = attachPageSeoMembership($user);
    $website = createPageSeoWebsite($account);
    $page = createPageSeoPage($website, $user);
    $mediaId = createPageSeoMedia($website, $user);
    createPageSeoRow($page->draftVersion, ['og_image_id' => $mediaId]);

    loginPageSeoUser($user);

    statefulPatchPageSeoRaw(pageSeoUri($account, $website, $page), '{"og_image_id":null}')
        ->assertOk()
        ->assertJsonPath('data.og_image_id', null);
});
