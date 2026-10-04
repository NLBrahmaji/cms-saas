<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\User;
use App\Models\Website;
use App\Navigation\NavigationItemType;
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

function navigationMetadataPatchUri(Account $account, Website $website, Navigation $navigation): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations/'.$navigation->id;
}

function navigationMetadataPatchOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncNavigationMetadataPatchCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPatchNavigationMetadata(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(navigationMetadataPatchOriginHeaders())->patchJson($uri, $data);

    syncNavigationMetadataPatchCookies($response);

    return $response;
}

function loginNavigationMetadataPatchUser(User $user): void
{
    test()->withHeaders(navigationMetadataPatchOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createNavigationMetadataPatchUser(): User
{
    return User::query()->create([
        'name' => 'Metadata User',
        'email' => 'nav-meta-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachNavigationMetadataPatchMembership(User $user, array $permissions): Account
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

function createNavigationMetadataPatchWebsite(Account $account, string $prefix = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site Name',
        'subdomain' => $prefix.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
        'published_at' => null,
    ]);
}

function createNavigationMetadataPatchNavigation(
    Website $website,
    string $name = 'Main Navigation',
    string $key = 'main',
): Navigation {
    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => $name,
        'key' => $key,
    ]);

    $user = User::query()->find($website->account_id);

    $version = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
        'created_by' => null,
    ]);

    $navigation->assignDraftVersion($version);

    return $navigation;
}

test('unauthenticated navigation metadata patch is unauthorized', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'New',
    ])->assertUnauthorized();
});

test('navigation metadata patch requires navigation update permission', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.view']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website);

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'New',
    ])->assertForbidden();
});

test('navigation publish permission alone cannot patch navigation metadata', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.publish']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website);

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'New',
    ])->assertForbidden();
});

test('page update permission alone cannot patch navigation metadata', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['page.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website);

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'New',
    ])->assertForbidden();
});

test('website update permission alone cannot patch navigation metadata', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['website.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website);

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'New',
    ])->assertForbidden();
});

test('navigation metadata patch returns not found for wrong website nesting', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $websiteA = createNavigationMetadataPatchWebsite($account, 'a');
    $websiteB = createNavigationMetadataPatchWebsite($account, 'b');
    $navigation = createNavigationMetadataPatchNavigation($websiteB);

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $websiteA, $navigation), [
        'name' => 'New',
    ])->assertNotFound();
});

test('navigation metadata patch validates request shape', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website);
    Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Footer',
        'key' => 'footer',
    ]);

    loginNavigationMetadataPatchUser($user);

    $uri = navigationMetadataPatchUri($account, $website, $navigation);

    statefulPatchNavigationMetadata($uri, [])->assertUnprocessable();

    statefulPatchNavigationMetadata($uri, ['name' => null])->assertUnprocessable();
    statefulPatchNavigationMetadata($uri, ['key' => null])->assertUnprocessable();

    statefulPatchNavigationMetadata($uri, [
        'name' => str_repeat('a', 256),
    ])->assertUnprocessable()->assertJsonValidationErrors(['name']);

    statefulPatchNavigationMetadata($uri, [
        'key' => str_repeat('a', 101),
    ])->assertUnprocessable()->assertJsonValidationErrors(['key']);

    statefulPatchNavigationMetadata($uri, ['key' => 'Main'])->assertUnprocessable();
    statefulPatchNavigationMetadata($uri, ['key' => 'foo bar'])->assertUnprocessable();
    statefulPatchNavigationMetadata($uri, ['key' => 'foo/bar'])->assertUnprocessable();

    statefulPatchNavigationMetadata($uri, [
        'draft_version_id' => 99,
    ])->assertUnprocessable();

    statefulPatchNavigationMetadata($uri, ['key' => 'footer'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key']);

    statefulPatchNavigationMetadata($uri, ['key' => 'main'])->assertOk();

    statefulPatchNavigationMetadata($uri, [
        'key' => 'utility_nav',
    ])->assertOk();

    statefulPatchNavigationMetadata($uri, [
        'key' => 'menu2',
    ])->assertOk();
});

test('navigation metadata patch allows key reused on different website', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $websiteA = createNavigationMetadataPatchWebsite($account, 'a');
    $websiteB = createNavigationMetadataPatchWebsite($account, 'b');
    $navigationA = createNavigationMetadataPatchNavigation($websiteA, 'Main', 'primary');
    createNavigationMetadataPatchNavigation($websiteB, 'Other', 'primary');

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $websiteA, $navigationA), [
        'key' => 'primary',
    ])->assertOk();
});

test('navigation metadata patch updates name only and preserves key', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website, 'Old', 'main');

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'Primary',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Primary')
        ->assertJsonPath('data.key', 'main');

    expect($navigation->refresh()->name)->toBe('Primary')
        ->and($navigation->key)->toBe('main');
});

test('navigation metadata patch updates key only and preserves name', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website, 'Main Navigation', 'main');

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'key' => 'primary',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Main Navigation')
        ->assertJsonPath('data.key', 'primary');
});

test('navigation metadata patch semantic no-op preserves updated at', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website, 'Main Navigation', 'main');

    loginNavigationMetadataPatchUser($user);

    $updatedAt = $navigation->updated_at;

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'Main Navigation',
        'key' => 'main',
    ])->assertOk();

    expect($navigation->refresh()->updated_at->eq($updatedAt))->toBeTrue();
});

test('navigation metadata patch meaningful change updates updated at', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website);

    loginNavigationMetadataPatchUser($user);

    $updatedAt = $navigation->updated_at;

    sleep(1);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'Renamed',
    ])->assertOk();

    expect($navigation->refresh()->updated_at->gt($updatedAt))->toBeTrue();
});

test('navigation metadata patch on published navigation does not clone or change pointers', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website);
    $draft = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();

    $item = NavigationItem::query()->create([
        'navigation_version_id' => $draft->id,
        'type' => NavigationItemType::Url,
        'url' => '/about',
        'sort_order' => 5,
        'label' => 'About',
        'open_in_new_tab' => true,
    ]);

    $navigation->assignPublishedVersion($draft);
    $draft->update([
        'published_at' => now(),
        'published_by' => $user->id,
    ]);

    $itemSnapshot = $item->only([
        'public_id',
        'parent_id',
        'type',
        'page_id',
        'url',
        'label',
        'sort_order',
        'open_in_new_tab',
    ]);
    $versionPublishedAt = $draft->published_at;
    $versionPublishedBy = $draft->published_by;
    $websiteName = $website->name;
    $websiteStatus = $website->status;

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'Primary Navigation',
    ])
        ->assertOk()
        ->assertJsonPath('data.has_published_version', true);

    $navigation->refresh();
    $draft->refresh();
    $website->refresh();
    $item->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and($navigation->draft_version_id)->toBe($draft->id)
        ->and($navigation->published_version_id)->toBe($draft->id)
        ->and($draft->published_at->eq($versionPublishedAt))->toBeTrue()
        ->and($draft->published_by)->toBe($versionPublishedBy)
        ->and($item->only([
            'public_id',
            'parent_id',
            'type',
            'page_id',
            'url',
            'label',
            'sort_order',
            'open_in_new_tab',
        ]))->toBe($itemSnapshot)
        ->and($website->name)->toBe($websiteName)
        ->and($website->status)->toBe($websiteStatus);
});

test('navigation metadata patch preserves ahead draft pointers', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website);

    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $navigation->assignPublishedVersion($v1);

    $v2 = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 2,
        'created_by' => $user->id,
    ]);
    $navigation->assignDraftVersion($v2);

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'name' => 'Renamed Menu',
    ])->assertOk();

    $navigation->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2)
        ->and($navigation->draft_version_id)->toBe($v2->id)
        ->and($navigation->published_version_id)->toBe($v1->id);
});

test('invalid navigation metadata patch leaves navigation unchanged', function () {
    $user = createNavigationMetadataPatchUser();
    $account = attachNavigationMetadataPatchMembership($user, ['navigation.update']);
    $website = createNavigationMetadataPatchWebsite($account);
    $navigation = createNavigationMetadataPatchNavigation($website, 'Stable', 'stable');
    Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Footer',
        'key' => 'footer',
    ]);

    $nameBefore = $navigation->name;
    $keyBefore = $navigation->key;
    $updatedAtBefore = $navigation->updated_at;

    loginNavigationMetadataPatchUser($user);

    statefulPatchNavigationMetadata(navigationMetadataPatchUri($account, $website, $navigation), [
        'key' => 'footer',
    ])->assertUnprocessable();

    $navigation->refresh();

    expect($navigation->name)->toBe($nameBefore)
        ->and($navigation->key)->toBe($keyBefore)
        ->and($navigation->updated_at->eq($updatedAtBefore))->toBeTrue();
});
