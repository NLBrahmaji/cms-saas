<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\Page;
use App\Models\PageVersion;
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

function navigationDeleteUri(Account $account, Website $website, Navigation $navigation): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations/'.$navigation->id;
}

function navigationDeleteItemsUri(Account $account, Website $website, Navigation $navigation): string
{
    return navigationDeleteUri($account, $website, $navigation).'/items';
}

function navigationDeletePublishUri(Account $account, Website $website, Navigation $navigation): string
{
    return navigationDeleteUri($account, $website, $navigation).'/publish';
}

function navigationDeleteOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncNavigationDeleteCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulDeleteNavigation(string $uri): TestResponse
{
    $response = test()->withHeaders(navigationDeleteOriginHeaders())->deleteJson($uri);

    syncNavigationDeleteCookies($response);

    return $response;
}

function statefulGetNavigationDelete(string $uri): TestResponse
{
    $response = test()->withHeaders(navigationDeleteOriginHeaders())->getJson($uri);

    syncNavigationDeleteCookies($response);

    return $response;
}

function statefulPatchNavigationDelete(string $uri, array $data): TestResponse
{
    $response = test()->withHeaders(navigationDeleteOriginHeaders())->patchJson($uri, $data);

    syncNavigationDeleteCookies($response);

    return $response;
}

function statefulPostNavigationDelete(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(navigationDeleteOriginHeaders())->postJson($uri, $data);

    syncNavigationDeleteCookies($response);

    return $response;
}

function loginNavigationDeleteUser(User $user): void
{
    statefulPostNavigationDelete('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createNavigationDeleteUser(): User
{
    return User::query()->create([
        'name' => 'Delete User',
        'email' => 'nav-del-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachNavigationDeleteMembership(User $user, array $permissions): Account
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

function createNavigationDeleteWebsite(Account $account, string $prefix = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site',
        'subdomain' => $prefix.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function createNavigationDeleteAggregate(
    Website $website,
    User $user,
    string $name = 'Menu',
    string $key = 'main',
): Navigation {
    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => $name,
        'key' => $key,
    ]);

    $version = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
        'created_by' => $user->id,
    ]);

    $navigation->assignDraftVersion($version);

    return $navigation;
}

function createNavigationDeleteVersion(Navigation $navigation, User $user, int $version): NavigationVersion
{
    return NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => $version,
        'created_by' => $user->id,
    ]);
}

test('unauthenticated navigation delete is unauthorized', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.delete']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user);

    statefulDeleteNavigation(navigationDeleteUri($account, $website, $navigation))
        ->assertUnauthorized();
});

test('navigation delete requires navigation delete permission', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.update']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user);

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation(navigationDeleteUri($account, $website, $navigation))
        ->assertForbidden();
});

test('navigation publish permission alone cannot delete navigation', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.publish']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user);

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation(navigationDeleteUri($account, $website, $navigation))
        ->assertForbidden();
});

test('navigation view permission alone cannot delete navigation', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.view']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user);

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation(navigationDeleteUri($account, $website, $navigation))
        ->assertForbidden();
});

test('website update permission alone cannot delete navigation', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['website.update']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user);

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation(navigationDeleteUri($account, $website, $navigation))
        ->assertForbidden();
});

test('navigation delete returns not found for wrong website nesting', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.delete']);
    $websiteA = createNavigationDeleteWebsite($account, 'a');
    $websiteB = createNavigationDeleteWebsite($account, 'b');
    $navigation = createNavigationDeleteAggregate($websiteB, $user);

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation(navigationDeleteUri($account, $websiteA, $navigation))
        ->assertNotFound();
});

test('navigation delete removes unpublished navigation and all owned rows', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.delete']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user);
    $draft = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();

    $parent = NavigationItem::query()->create([
        'navigation_version_id' => $draft->id,
        'type' => NavigationItemType::Url,
        'url' => '/parent',
        'sort_order' => 0,
    ]);
    NavigationItem::query()->create([
        'navigation_version_id' => $draft->id,
        'type' => NavigationItemType::Url,
        'url' => '/child',
        'parent_id' => $parent->id,
        'sort_order' => 1,
    ]);

    $navigationId = $navigation->id;
    $versionCountBefore = NavigationVersion::query()->count();

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation(navigationDeleteUri($account, $website, $navigation))
        ->assertNoContent();

    expect(Navigation::query()->find($navigationId))->toBeNull()
        ->and(NavigationVersion::query()->where('navigation_id', $navigationId)->count())->toBe(0)
        ->and(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe(0)
        ->and(NavigationVersion::query()->count())->toBe($versionCountBefore - 1);
});

test('navigation delete removes ahead draft and published versions', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.delete']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user);

    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    createNavigationDeleteVersion($navigation, $user, 2);
    createNavigationDeleteVersion($navigation, $user, 3);
    $v4 = createNavigationDeleteVersion($navigation, $user, 4);

    NavigationItem::query()->create([
        'navigation_version_id' => $v1->id,
        'type' => NavigationItemType::Url,
        'url' => '/v1',
    ]);
    NavigationItem::query()->create([
        'navigation_version_id' => $v4->id,
        'type' => NavigationItemType::Url,
        'url' => '/v4',
    ]);

    $navigation->assignPublishedVersion($v1);
    $navigation->assignDraftVersion($v4);

    $navigationId = $navigation->id;

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation(navigationDeleteUri($account, $website, $navigation))
        ->assertNoContent();

    expect(Navigation::query()->find($navigationId))->toBeNull()
        ->and(NavigationVersion::query()->where('navigation_id', $navigationId)->count())->toBe(0)
        ->and(NavigationItem::query()->whereIn('navigation_version_id', [$v1->id, $v4->id])->count())->toBe(0);
});

test('navigation delete preserves pages website and other navigations', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.delete', 'navigation.create']);
    $website = createNavigationDeleteWebsite($account);

    $page = Page::query()->create(['website_id' => $website->id]);
    PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'About',
        'slug' => 'about',
        'created_by' => $user->id,
    ]);

    $target = createNavigationDeleteAggregate($website, $user, 'Target', 'target');
    $draft = NavigationVersion::query()->where('navigation_id', $target->id)->firstOrFail();
    NavigationItem::query()->create([
        'navigation_version_id' => $draft->id,
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'url' => null,
    ]);
    NavigationItem::query()->create([
        'navigation_version_id' => $draft->id,
        'type' => NavigationItemType::Url,
        'url' => '//invalid.example',
    ]);

    $other = createNavigationDeleteAggregate($website, $user, 'Other', 'other');
    $otherDraft = NavigationVersion::query()->where('navigation_id', $other->id)->firstOrFail();
    $otherItem = NavigationItem::query()->create([
        'navigation_version_id' => $otherDraft->id,
        'type' => NavigationItemType::Url,
        'url' => '/stay',
    ]);

    $websiteName = $website->name;
    $websiteStatus = $website->status;

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation(navigationDeleteUri($account, $website, $target))
        ->assertNoContent();

    $website->refresh();

    expect(Page::query()->find($page->id))->not->toBeNull()
        ->and(Navigation::query()->find($other->id))->not->toBeNull()
        ->and(NavigationItem::query()->find($otherItem->id))->not->toBeNull()
        ->and($website->name)->toBe($websiteName)
        ->and($website->status)->toBe($websiteStatus);
});

test('navigation delete allows key reuse on same website', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.delete', 'navigation.create']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user, 'Main', 'main');

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation(navigationDeleteUri($account, $website, $navigation))
        ->assertNoContent();

    statefulPostNavigationDelete('/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations', [
        'name' => 'Main again',
        'key' => 'main',
    ])->assertCreated();
});

test('deleted navigation routes return not found', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.delete', 'navigation.update', 'navigation.publish', 'navigation.view']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user);
    $draft = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $item = NavigationItem::query()->create([
        'navigation_version_id' => $draft->id,
        'type' => NavigationItemType::Url,
        'url' => '/x',
    ]);

    $uri = navigationDeleteUri($account, $website, $navigation);

    loginNavigationDeleteUser($user);

    statefulDeleteNavigation($uri)->assertNoContent();

    statefulGetNavigationDelete($uri)->assertNotFound();
    statefulPatchNavigationDelete($uri, ['name' => 'Nope'])->assertNotFound();
    statefulPostNavigationDelete(navigationDeletePublishUri($account, $website, $navigation))->assertNotFound();
    statefulGetNavigationDelete(navigationDeleteItemsUri($account, $website, $navigation))->assertNotFound();
    statefulPatchNavigationDelete(navigationDeleteItemsUri($account, $website, $navigation).'/'.$item->public_id, [
        'label' => 'Nope',
    ])->assertNotFound();
});

test('navigation delete rolls back when version deletion fails', function () {
    $user = createNavigationDeleteUser();
    $account = attachNavigationDeleteMembership($user, ['navigation.delete']);
    $website = createNavigationDeleteWebsite($account);
    $navigation = createNavigationDeleteAggregate($website, $user);
    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $v2 = createNavigationDeleteVersion($navigation, $user, 2);
    $navigation->assignPublishedVersion($v1);
    $navigation->assignDraftVersion($v2);

    NavigationItem::query()->create([
        'navigation_version_id' => $v1->id,
        'type' => NavigationItemType::Url,
        'url' => '/a',
    ]);
    NavigationItem::query()->create([
        'navigation_version_id' => $v2->id,
        'type' => NavigationItemType::Url,
        'url' => '/b',
    ]);

    loginNavigationDeleteUser($user);

    NavigationVersion::deleting(function (): void {
        throw new RuntimeException('Simulated navigation version delete failure');
    });

    try {
        statefulDeleteNavigation(navigationDeleteUri($account, $website, $navigation))
            ->assertStatus(500);
    } finally {
        NavigationVersion::flushEventListeners();
    }

    $navigation->refresh();

    expect($navigation->draft_version_id)->toBe($v2->id)
        ->and($navigation->published_version_id)->toBe($v1->id)
        ->and(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2)
        ->and(NavigationItem::query()->count())->toBe(2);
});
