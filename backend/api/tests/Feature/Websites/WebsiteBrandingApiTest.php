<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Media;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteBranding;
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

function websiteBrandingOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncWebsiteBrandingCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetWebsiteBranding(string $uri): TestResponse
{
    $response = test()->withHeaders(websiteBrandingOriginHeaders())->getJson($uri);

    syncWebsiteBrandingCookies($response);

    return $response;
}

function statefulPatchWebsiteBranding(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(websiteBrandingOriginHeaders())->patchJson($uri, $data);

    syncWebsiteBrandingCookies($response);

    return $response;
}

function statefulPatchWebsiteBrandingRaw(string $uri, string $json): TestResponse
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

    syncWebsiteBrandingCookies($response);

    return $response;
}

function statefulPostWebsiteBrandingLogin(array $data): TestResponse
{
    $response = test()->withHeaders(websiteBrandingOriginHeaders())->postJson('/v1/auth/login', $data);

    syncWebsiteBrandingCookies($response);

    return $response;
}

function createWebsiteBrandingUser(): User
{
    return User::query()->create([
        'name' => 'Branding User',
        'email' => 'branding-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function loginWebsiteBrandingUser(User $user): void
{
    statefulPostWebsiteBrandingLogin([
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();

    Auth::forgetGuards();
}

function attachWebsiteBrandingMembership(User $user, array $permissions): Account
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
        'joined_at' => now(),
    ]);

    $role = Role::query()->create([
        'name' => 'role-'.uniqid(),
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

function createWebsiteBrandingWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site',
        'subdomain' => 'site-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function accountWebsiteBrandingUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/branding';
}

/**
 * @return array<string, mixed>
 */
function expectedWebsiteBrandingPayload(
    ?int $logoMediaId = null,
    ?int $logoLightMediaId = null,
    ?int $logoDarkMediaId = null,
    ?int $faviconMediaId = null,
): array {
    return [
        'logo_media_id' => $logoMediaId,
        'logo_light_media_id' => $logoLightMediaId,
        'logo_dark_media_id' => $logoDarkMediaId,
        'favicon_media_id' => $faviconMediaId,
    ];
}

function createWebsiteBrandingMedia(Website $website, User $user, string $mimeType = 'image/jpeg'): int
{
    return (int) DB::table('media')->insertGetId([
        'website_id' => $website->id,
        'disk' => 'public',
        'path' => 'images/asset-'.uniqid().'.jpg',
        'original_name' => 'asset.jpg',
        'mime_type' => $mimeType,
        'extension' => 'jpg',
        'size' => 1024,
        'source' => 'upload',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function softDeleteWebsiteBrandingMedia(int $mediaId): void
{
    Media::query()->whereKey($mediaId)->delete();
}

test('unauthenticated website branding get and patch are unauthorized', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.view', 'website.update']);
    $website = createWebsiteBrandingWebsite($account);

    statefulGetWebsiteBranding(accountWebsiteBrandingUri($account, $website))->assertUnauthorized();
    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), ['logo_media_id' => null])
        ->assertUnauthorized();
});

test('website branding get returns synthetic nulls without creating row', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.view']);
    $website = createWebsiteBrandingWebsite($account);

    loginWebsiteBrandingUser($user);

    statefulGetWebsiteBranding(accountWebsiteBrandingUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteBrandingPayload()]);

    expect(WebsiteBranding::query()->count())->toBe(0);
});

test('website branding get returns stored media ids including stale references', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.view']);
    $website = createWebsiteBrandingWebsite($account);
    $logoId = createWebsiteBrandingMedia($website, $user);

    WebsiteBranding::query()->create([
        'website_id' => $website->id,
        'logo_media_id' => $logoId,
        'favicon_media_id' => null,
    ]);

    softDeleteWebsiteBrandingMedia($logoId);

    loginWebsiteBrandingUser($user);

    $payload = statefulGetWebsiteBranding(accountWebsiteBrandingUri($account, $website))
        ->assertOk()
        ->json('data');

    expect($payload)->toBe(expectedWebsiteBrandingPayload(logoMediaId: $logoId))
        ->and($payload)->not->toHaveKeys(['id', 'website_id', 'theme', 'created_at', 'updated_at', 'url']);
});

test('website view permission is required for branding get', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);

    loginWebsiteBrandingUser($user);

    statefulGetWebsiteBranding(accountWebsiteBrandingUri($account, $website))->assertForbidden();
});

test('website update permission is required for branding patch', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.view']);
    $website = createWebsiteBrandingWebsite($account);

    loginWebsiteBrandingUser($user);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), ['logo_media_id' => null])
        ->assertForbidden();
});

test('website branding tenant isolation returns not found for wrong nesting', function () {
    $user = createWebsiteBrandingUser();
    $accountA = attachWebsiteBrandingMembership($user, ['website.view', 'website.update']);
    $otherUser = createWebsiteBrandingUser();
    $accountB = attachWebsiteBrandingMembership($otherUser, ['website.view']);
    $websiteOnA = createWebsiteBrandingWebsite($accountA);
    $websiteOnB = createWebsiteBrandingWebsite($accountB);

    loginWebsiteBrandingUser($user);

    statefulGetWebsiteBranding('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/branding')
        ->assertNotFound();

    statefulPatchWebsiteBranding('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/branding', [
        'logo_media_id' => null,
    ])->assertNotFound();
});

test('website branding patch accepts supported raster mime types', function (string $mimeType, string $field) {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $mediaId = createWebsiteBrandingMedia($website, $user, $mimeType);

    loginWebsiteBrandingUser($user);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        $field => $mediaId,
    ])->assertOk();
})->with([
    ['image/jpeg', 'logo_media_id'],
    ['image/png', 'logo_light_media_id'],
    ['image/webp', 'logo_dark_media_id'],
    ['image/gif', 'favicon_media_id'],
]);

test('website branding patch rejects invalid media targets', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $otherWebsite = createWebsiteBrandingWebsite($account);
    $otherUser = createWebsiteBrandingUser();
    $otherAccount = attachWebsiteBrandingMembership($otherUser, ['website.update']);
    $foreignWebsite = createWebsiteBrandingWebsite($otherAccount);
    $uri = accountWebsiteBrandingUri($account, $website);

    $otherSiteMedia = createWebsiteBrandingMedia($otherWebsite, $user);
    $foreignMedia = createWebsiteBrandingMedia($foreignWebsite, $otherUser);
    $deletedMedia = createWebsiteBrandingMedia($website, $user);
    softDeleteWebsiteBrandingMedia($deletedMedia);
    $unsupported = createWebsiteBrandingMedia($website, $user, 'application/pdf');

    loginWebsiteBrandingUser($user);

    statefulPatchWebsiteBranding($uri, ['logo_media_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo_media_id']);

    statefulPatchWebsiteBranding($uri, ['logo_media_id' => $otherSiteMedia])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo_media_id']);

    statefulPatchWebsiteBranding($uri, ['favicon_media_id' => $foreignMedia])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['favicon_media_id']);

    statefulPatchWebsiteBranding($uri, ['logo_light_media_id' => $deletedMedia])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo_light_media_id']);

    statefulPatchWebsiteBranding($uri, ['logo_dark_media_id' => $unsupported])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo_dark_media_id']);

    statefulPatchWebsiteBrandingRaw($uri, '{"logo_media_id":"10"}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['logo_media_id']);

    statefulPatchWebsiteBranding($uri, [])->assertUnprocessable();
    statefulPatchWebsiteBranding($uri, ['theme' => ['primary_color' => '#000']])->assertUnprocessable();
    statefulPatchWebsiteBranding($uri, ['unexpected' => 1])->assertUnprocessable();
});

test('website branding missing row null patch is semantic no op', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);

    loginWebsiteBrandingUser($user);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), ['logo_media_id' => null])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteBrandingPayload()]);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'logo_media_id' => null,
        'favicon_media_id' => null,
    ])->assertOk();

    expect(WebsiteBranding::query()->count())->toBe(0);
});

test('website branding lazily creates row on meaningful media selection', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $logoId = createWebsiteBrandingMedia($website, $user);

    loginWebsiteBrandingUser($user);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), ['logo_media_id' => $logoId])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteBrandingPayload(logoMediaId: $logoId)]);

    $branding = WebsiteBranding::query()->where('website_id', $website->id)->first();

    expect($branding)->not->toBeNull()
        ->and($branding->logo_light_media_id)->toBeNull()
        ->and($branding->theme)->toBeNull();
});

test('website branding partial update preserves omitted media slots', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $logo = createWebsiteBrandingMedia($website, $user);
    $favicon = createWebsiteBrandingMedia($website, $user, 'image/png');

    WebsiteBranding::query()->create([
        'website_id' => $website->id,
        'logo_media_id' => $logo,
        'favicon_media_id' => $favicon,
    ]);

    loginWebsiteBrandingUser($user);

    $newLogo = createWebsiteBrandingMedia($website, $user, 'image/webp');

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'logo_media_id' => $newLogo,
    ])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteBrandingPayload(
            logoMediaId: $newLogo,
            faviconMediaId: $favicon,
        )]);
});

test('website branding patch clears individual media slots with null', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $dark = createWebsiteBrandingMedia($website, $user);

    WebsiteBranding::query()->create([
        'website_id' => $website->id,
        'logo_dark_media_id' => $dark,
        'logo_media_id' => createWebsiteBrandingMedia($website, $user),
    ]);

    loginWebsiteBrandingUser($user);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'logo_dark_media_id' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.logo_dark_media_id', null)
        ->assertJsonPath('data.logo_media_id', WebsiteBranding::query()->first()?->logo_media_id);
});

test('website branding same active media id patch is semantic no op', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $logoId = createWebsiteBrandingMedia($website, $user);

    $branding = WebsiteBranding::query()->create([
        'website_id' => $website->id,
        'logo_media_id' => $logoId,
    ]);

    loginWebsiteBrandingUser($user);

    $updatedAt = $branding->updated_at;

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), ['logo_media_id' => $logoId])
        ->assertOk();

    expect($branding->refresh()->updated_at->eq($updatedAt))->toBeTrue();
});

test('website branding meaningful update changes updated at', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $logoId = createWebsiteBrandingMedia($website, $user);

    $branding = WebsiteBranding::query()->create([
        'website_id' => $website->id,
        'logo_media_id' => $logoId,
    ]);

    loginWebsiteBrandingUser($user);

    $updatedAt = $branding->updated_at;
    $newLogo = createWebsiteBrandingMedia($website, $user, 'image/png');

    sleep(1);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), ['logo_media_id' => $newLogo])
        ->assertOk();

    expect($branding->refresh()->updated_at->gt($updatedAt))->toBeTrue();
});

test('website branding stale stored logo allows unrelated slot update', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.view', 'website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $logoA = createWebsiteBrandingMedia($website, $user);
    $faviconB = createWebsiteBrandingMedia($website, $user, 'image/png');

    WebsiteBranding::query()->create([
        'website_id' => $website->id,
        'logo_media_id' => $logoA,
    ]);

    softDeleteWebsiteBrandingMedia($logoA);

    loginWebsiteBrandingUser($user);

    statefulGetWebsiteBranding(accountWebsiteBrandingUri($account, $website))
        ->assertJsonPath('data.logo_media_id', $logoA);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'favicon_media_id' => $faviconB,
    ])
        ->assertOk()
        ->assertJsonPath('data.logo_media_id', $logoA)
        ->assertJsonPath('data.favicon_media_id', $faviconB);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'logo_media_id' => $logoA,
    ])->assertUnprocessable()->assertJsonValidationErrors(['logo_media_id']);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'logo_media_id' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.logo_media_id', null);
});

test('website branding patch is atomic when one media field is invalid', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $validLogo = createWebsiteBrandingMedia($website, $user);

    loginWebsiteBrandingUser($user);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'logo_media_id' => $validLogo,
        'favicon_media_id' => 999999,
    ])->assertUnprocessable();

    expect(WebsiteBranding::query()->count())->toBe(0);

    WebsiteBranding::query()->create([
        'website_id' => $website->id,
        'logo_media_id' => $validLogo,
    ]);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'logo_media_id' => createWebsiteBrandingMedia($website, $user, 'image/png'),
        'favicon_media_id' => 999999,
    ])->assertUnprocessable();

    expect(WebsiteBranding::query()->first()?->logo_media_id)->toBe($validLogo);
});

test('website branding patch preserves hidden theme json', function () {
    $user = createWebsiteBrandingUser();
    $account = attachWebsiteBrandingMembership($user, ['website.update']);
    $website = createWebsiteBrandingWebsite($account);
    $favicon = createWebsiteBrandingMedia($website, $user);

    $theme = ['primary_color' => '#123456', 'nested' => ['font' => 'Inter']];

    $branding = WebsiteBranding::query()->create([
        'website_id' => $website->id,
        'favicon_media_id' => createWebsiteBrandingMedia($website, $user),
        'theme' => $theme,
    ]);

    loginWebsiteBrandingUser($user);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'favicon_media_id' => $favicon,
        'logo_media_id' => null,
    ])->assertOk();

    expect($branding->refresh()->theme)->toBe($theme);

    statefulPatchWebsiteBranding(accountWebsiteBrandingUri($account, $website), [
        'logo_media_id' => null,
        'logo_light_media_id' => null,
        'logo_dark_media_id' => null,
        'favicon_media_id' => null,
    ])->assertOk();

    expect(WebsiteBranding::query()->where('website_id', $website->id)->exists())->toBeTrue()
        ->and($branding->refresh()->theme)->toBe($theme);
});
