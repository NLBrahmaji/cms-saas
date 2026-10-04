<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Media;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteSeoSetting;
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

function websiteSeoOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncWebsiteSeoCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetWebsiteSeo(string $uri): TestResponse
{
    $response = test()->withHeaders(websiteSeoOriginHeaders())->getJson($uri);

    syncWebsiteSeoCookies($response);

    return $response;
}

function statefulPatchWebsiteSeo(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(websiteSeoOriginHeaders())->patchJson($uri, $data);

    syncWebsiteSeoCookies($response);

    return $response;
}

function statefulPatchWebsiteSeoRaw(string $uri, string $json): TestResponse
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

    syncWebsiteSeoCookies($response);

    return $response;
}

function statefulPostWebsiteSeoLogin(array $data): TestResponse
{
    $response = test()->withHeaders(websiteSeoOriginHeaders())->postJson('/v1/auth/login', $data);

    syncWebsiteSeoCookies($response);

    return $response;
}

function createWebsiteSeoUser(): User
{
    return User::query()->create([
        'name' => 'SEO User',
        'email' => 'website-seo-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function loginWebsiteSeoUser(User $user): void
{
    statefulPostWebsiteSeoLogin([
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();

    Auth::forgetGuards();
}

function attachWebsiteSeoMembership(User $user, array $permissions): Account
{
    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => 'Account',
        'status' => 'active',
    ]);

    AccountMember::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    $role = Role::create([
        'name' => 'website-seo-'.uniqid(),
        'guard_name' => 'web',
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

function createWebsiteSeoWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site',
        'subdomain' => 'site-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function accountWebsiteSeoUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/seo';
}

/**
 * @return array<string, mixed>
 */
function expectedWebsiteSeoPayload(
    ?string $titleSuffix = null,
    ?string $defaultDescription = null,
    ?int $defaultOgImageId = null,
    bool $robotsIndex = true,
    bool $robotsFollow = true,
): array {
    return [
        'title_suffix' => $titleSuffix,
        'default_description' => $defaultDescription,
        'default_og_image_id' => $defaultOgImageId,
        'robots_index' => $robotsIndex,
        'robots_follow' => $robotsFollow,
    ];
}

function createWebsiteSeoMedia(Website $website, User $user, string $mimeType = 'image/jpeg'): int
{
    return (int) DB::table('media')->insertGetId([
        'website_id' => $website->id,
        'disk' => 'public',
        'path' => 'websites/'.$website->id.'/media/'.uniqid().'.jpg',
        'original_name' => 'image.jpg',
        'mime_type' => $mimeType,
        'extension' => 'jpg',
        'size' => 1024,
        'source' => 'upload',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function softDeleteWebsiteSeoMedia(int $mediaId): void
{
    Media::query()->whereKey($mediaId)->delete();
}

test('unauthenticated website seo get and patch are unauthorized', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.view', 'website.update']);
    $website = createWebsiteSeoWebsite($account);

    statefulGetWebsiteSeo(accountWebsiteSeoUri($account, $website))->assertUnauthorized();
    statefulPatchWebsiteSeo(accountWebsiteSeoUri($account, $website), ['title_suffix' => null])
        ->assertUnauthorized();
});

test('website seo get returns synthetic defaults without creating row', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.view']);
    $website = createWebsiteSeoWebsite($account);

    loginWebsiteSeoUser($user);

    statefulGetWebsiteSeo(accountWebsiteSeoUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSeoPayload()]);

    expect(WebsiteSeoSetting::query()->count())->toBe(0);
});

test('website seo get returns stored values including stale og image id', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.view']);
    $website = createWebsiteSeoWebsite($account);
    $imageId = createWebsiteSeoMedia($website, $user);

    WebsiteSeoSetting::query()->create([
        'website_id' => $website->id,
        'title_suffix' => '| Acme',
        'default_description' => 'Site description',
        'default_og_image_id' => $imageId,
        'robots_index' => false,
        'robots_follow' => true,
    ]);

    softDeleteWebsiteSeoMedia($imageId);

    loginWebsiteSeoUser($user);

    $payload = statefulGetWebsiteSeo(accountWebsiteSeoUri($account, $website))
        ->assertOk()
        ->json('data');

    expect($payload)->toBe(expectedWebsiteSeoPayload(
        titleSuffix: '| Acme',
        defaultDescription: 'Site description',
        defaultOgImageId: $imageId,
        robotsIndex: false,
        robotsFollow: true,
    ))->and($payload)->not->toHaveKeys(['id', 'website_id', 'created_at', 'updated_at', 'url']);
});

test('website view permission is required for seo get', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);

    loginWebsiteSeoUser($user);

    statefulGetWebsiteSeo(accountWebsiteSeoUri($account, $website))->assertForbidden();
});

test('website update permission is required for seo patch', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.view']);
    $website = createWebsiteSeoWebsite($account);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeo(accountWebsiteSeoUri($account, $website), ['title_suffix' => '| Acme'])
        ->assertForbidden();
});

test('website seo tenant isolation returns not found for wrong nesting', function () {
    $user = createWebsiteSeoUser();
    $accountA = attachWebsiteSeoMembership($user, ['website.view', 'website.update']);
    $otherUser = createWebsiteSeoUser();
    $accountB = attachWebsiteSeoMembership($otherUser, ['website.view']);
    $websiteOnB = createWebsiteSeoWebsite($accountB);

    loginWebsiteSeoUser($user);

    statefulGetWebsiteSeo('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/seo')
        ->assertNotFound();

    statefulPatchWebsiteSeo('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/seo', [
        'title_suffix' => '| Nope',
    ])->assertNotFound();
});

test('website seo patch validates strings and rejects oversize title suffix', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);
    $uri = accountWebsiteSeoUri($account, $website);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeo($uri, ['title_suffix' => '| Acme'])
        ->assertOk()
        ->assertJsonPath('data.title_suffix', '| Acme');

    statefulPatchWebsiteSeo($uri, ['title_suffix' => null])
        ->assertOk()
        ->assertJsonPath('data.title_suffix', null);

    statefulPatchWebsiteSeo($uri, ['title_suffix' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title_suffix']);

    statefulPatchWebsiteSeoRaw($uri, '{"title_suffix":123}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title_suffix']);

    statefulPatchWebsiteSeo($uri, ['default_description' => 'A long site description without an invented max.'])
        ->assertOk();

    statefulPatchWebsiteSeo($uri, ['default_description' => null])->assertOk();

    statefulPatchWebsiteSeoRaw($uri, '{"default_description":false}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_description']);
});

test('website seo patch validates robots as strict booleans only', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);
    $uri = accountWebsiteSeoUri($account, $website);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeoRaw($uri, '{"robots_index":true,"robots_follow":false}')
        ->assertOk()
        ->assertJsonPath('data.robots_index', true)
        ->assertJsonPath('data.robots_follow', false);

    statefulPatchWebsiteSeoRaw($uri, '{"robots_index":null}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['robots_index']);

    statefulPatchWebsiteSeoRaw($uri, '{"robots_follow":0}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['robots_follow']);

    statefulPatchWebsiteSeoRaw($uri, '{"robots_index":1}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['robots_index']);

    statefulPatchWebsiteSeoRaw($uri, '{"robots_index":"false"}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['robots_index']);
});

test('website seo missing row default robots patch is semantic no op', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);
    $uri = accountWebsiteSeoUri($account, $website);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeoRaw($uri, '{"robots_index":true,"robots_follow":true}')
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSeoPayload()]);

    statefulPatchWebsiteSeo($uri, [
        'title_suffix' => null,
        'default_description' => null,
        'default_og_image_id' => null,
    ])->assertOk();

    expect(WebsiteSeoSetting::query()->count())->toBe(0);
});

test('website seo missing row meaningful robots false creates row with defaults', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeoRaw(accountWebsiteSeoUri($account, $website), '{"robots_index":false}')
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSeoPayload(robotsIndex: false)]);

    $seo = WebsiteSeoSetting::query()->where('website_id', $website->id)->first();

    expect($seo)->not->toBeNull()
        ->and($seo->title_suffix)->toBeNull()
        ->and($seo->default_description)->toBeNull()
        ->and($seo->default_og_image_id)->toBeNull()
        ->and($seo->robots_index)->toBeFalse()
        ->and($seo->robots_follow)->toBeTrue();
});

test('website seo lazily creates row on meaningful string update', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeo(accountWebsiteSeoUri($account, $website), ['title_suffix' => '| Acme'])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSeoPayload(titleSuffix: '| Acme')]);

    $seo = WebsiteSeoSetting::query()->where('website_id', $website->id)->first();

    expect($seo)->not->toBeNull()
        ->and($seo->robots_index)->toBeTrue()
        ->and($seo->robots_follow)->toBeTrue();
});

test('website seo patch accepts supported raster og image mime types', function (string $mimeType) {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);
    $mediaId = createWebsiteSeoMedia($website, $user, $mimeType);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeo(accountWebsiteSeoUri($account, $website), [
        'default_og_image_id' => $mediaId,
    ])->assertOk();
})->with([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
]);

test('website seo patch rejects invalid og image targets', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);
    $otherWebsite = createWebsiteSeoWebsite($account);
    $otherUser = createWebsiteSeoUser();
    $otherAccount = attachWebsiteSeoMembership($otherUser, ['website.update']);
    $foreignWebsite = createWebsiteSeoWebsite($otherAccount);
    $uri = accountWebsiteSeoUri($account, $website);

    $otherSiteMedia = createWebsiteSeoMedia($otherWebsite, $user);
    $foreignMedia = createWebsiteSeoMedia($foreignWebsite, $otherUser);
    $deletedMedia = createWebsiteSeoMedia($website, $user);
    softDeleteWebsiteSeoMedia($deletedMedia);
    $unsupported = createWebsiteSeoMedia($website, $user, 'application/pdf');

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeo($uri, ['default_og_image_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_og_image_id']);

    statefulPatchWebsiteSeo($uri, ['default_og_image_id' => $otherSiteMedia])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_og_image_id']);

    statefulPatchWebsiteSeo($uri, ['default_og_image_id' => $foreignMedia])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_og_image_id']);

    statefulPatchWebsiteSeo($uri, ['default_og_image_id' => $deletedMedia])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_og_image_id']);

    statefulPatchWebsiteSeo($uri, ['default_og_image_id' => $unsupported])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_og_image_id']);

    statefulPatchWebsiteSeoRaw($uri, '{"default_og_image_id":"10"}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_og_image_id']);

    statefulPatchWebsiteSeo($uri, [])->assertUnprocessable();
    statefulPatchWebsiteSeo($uri, ['canonical_url' => 'https://example.com'])->assertUnprocessable();
    statefulPatchWebsiteSeo($uri, ['meta_title' => 'Title'])->assertUnprocessable();
    statefulPatchWebsiteSeo($uri, ['unexpected' => 1])->assertUnprocessable();
});

test('website seo partial patch preserves omitted fields', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);

    WebsiteSeoSetting::query()->create([
        'website_id' => $website->id,
        'title_suffix' => '| Acme',
        'default_description' => 'Keep',
        'robots_index' => true,
        'robots_follow' => true,
    ]);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeoRaw(accountWebsiteSeoUri($account, $website), '{"robots_index":false}')
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSeoPayload(
            titleSuffix: '| Acme',
            defaultDescription: 'Keep',
            robotsIndex: false,
        )]);
});

test('website seo same state patch is semantic no op and preserves updated at', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);
    $imageId = createWebsiteSeoMedia($website, $user);

    $seo = WebsiteSeoSetting::query()->create([
        'website_id' => $website->id,
        'title_suffix' => '| Acme',
        'default_description' => 'Desc',
        'default_og_image_id' => $imageId,
        'robots_index' => true,
        'robots_follow' => false,
    ]);

    loginWebsiteSeoUser($user);

    $updatedAt = $seo->updated_at;

    statefulPatchWebsiteSeo(accountWebsiteSeoUri($account, $website), [
        'title_suffix' => '| Acme',
        'default_description' => 'Desc',
        'default_og_image_id' => $imageId,
        'robots_index' => true,
        'robots_follow' => false,
    ])->assertOk();

    expect($seo->refresh()->updated_at->eq($updatedAt))->toBeTrue();
});

test('website seo stale og image allows unrelated patch and rejects explicit reselection', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.view', 'website.update']);
    $website = createWebsiteSeoWebsite($account);
    $imageA = createWebsiteSeoMedia($website, $user);

    WebsiteSeoSetting::query()->create([
        'website_id' => $website->id,
        'default_og_image_id' => $imageA,
    ]);

    softDeleteWebsiteSeoMedia($imageA);

    loginWebsiteSeoUser($user);

    statefulGetWebsiteSeo(accountWebsiteSeoUri($account, $website))
        ->assertJsonPath('data.default_og_image_id', $imageA);

    statefulPatchWebsiteSeo(accountWebsiteSeoUri($account, $website), [
        'title_suffix' => '| New',
    ])
        ->assertOk()
        ->assertJsonPath('data.default_og_image_id', $imageA)
        ->assertJsonPath('data.title_suffix', '| New');

    statefulPatchWebsiteSeo(accountWebsiteSeoUri($account, $website), [
        'default_og_image_id' => $imageA,
    ])->assertUnprocessable()->assertJsonValidationErrors(['default_og_image_id']);

    statefulPatchWebsiteSeo(accountWebsiteSeoUri($account, $website), [
        'default_og_image_id' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.default_og_image_id', null);
});

test('website seo patch is atomic when og image is invalid', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);
    $uri = accountWebsiteSeoUri($account, $website);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeo($uri, [
        'title_suffix' => '| New',
        'default_og_image_id' => 999999,
    ])->assertUnprocessable();

    expect(WebsiteSeoSetting::query()->count())->toBe(0);

    WebsiteSeoSetting::query()->create([
        'website_id' => $website->id,
        'title_suffix' => '| Old',
    ]);

    statefulPatchWebsiteSeo($uri, [
        'title_suffix' => '| Newer',
        'default_og_image_id' => 999999,
    ])->assertUnprocessable();

    expect(WebsiteSeoSetting::query()->first()?->title_suffix)->toBe('| Old');
});

test('website seo patch is atomic when robots are invalid', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);
    $uri = accountWebsiteSeoUri($account, $website);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeoRaw($uri, '{"title_suffix":"| New","robots_index":null}')
        ->assertUnprocessable();

    expect(WebsiteSeoSetting::query()->count())->toBe(0);
});

test('website seo reset to effective defaults retains existing row', function () {
    $user = createWebsiteSeoUser();
    $account = attachWebsiteSeoMembership($user, ['website.update']);
    $website = createWebsiteSeoWebsite($account);
    $imageId = createWebsiteSeoMedia($website, $user);

    $seo = WebsiteSeoSetting::query()->create([
        'website_id' => $website->id,
        'title_suffix' => '| Acme',
        'default_description' => 'Desc',
        'default_og_image_id' => $imageId,
        'robots_index' => false,
        'robots_follow' => false,
    ]);

    loginWebsiteSeoUser($user);

    statefulPatchWebsiteSeo(accountWebsiteSeoUri($account, $website), [
        'title_suffix' => null,
        'default_description' => null,
        'default_og_image_id' => null,
        'robots_index' => true,
        'robots_follow' => true,
    ])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSeoPayload()]);

    expect(WebsiteSeoSetting::query()->where('website_id', $website->id)->exists())->toBeTrue()
        ->and($seo->refresh()->title_suffix)->toBeNull()
        ->and($seo->default_og_image_id)->toBeNull()
        ->and($seo->robots_index)->toBeTrue()
        ->and($seo->robots_follow)->toBeTrue();
});
