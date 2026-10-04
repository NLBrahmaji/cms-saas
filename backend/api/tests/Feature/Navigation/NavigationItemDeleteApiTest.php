<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\User;
use App\Models\Website;
use App\Navigation\NavigationItemType;
use App\Support\Navigation\NavigationItemDeleter;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function navigationItemDeleteUri(
    Account $account,
    Website $website,
    Navigation $navigation,
    NavigationItem|string $item,
): string {
    $itemId = $item instanceof NavigationItem ? $item->public_id : $item;

    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations/'.$navigation->id.'/items/'.$itemId;
}

function navigationItemsDeleteListUri(Account $account, Website $website, Navigation $navigation): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations/'.$navigation->id.'/items';
}

function navigationItemDeleteOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncNavigationItemDeleteCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulDeleteNavigationItem(string $uri): TestResponse
{
    $response = test()->withHeaders(navigationItemDeleteOriginHeaders())->deleteJson($uri);

    syncNavigationItemDeleteCookies($response);

    return $response;
}

function statefulGetNavigationItemsForDelete(string $uri): TestResponse
{
    $response = test()->withHeaders(navigationItemDeleteOriginHeaders())->getJson($uri);

    syncNavigationItemDeleteCookies($response);

    return $response;
}

function statefulPutNavigationItemReorderForDelete(string $uri, array $data): TestResponse
{
    $response = test()->withHeaders(navigationItemDeleteOriginHeaders())->putJson($uri, $data);

    syncNavigationItemDeleteCookies($response);

    return $response;
}

function loginNavigationItemDeleteUser(User $user): void
{
    test()->withHeaders(navigationItemDeleteOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createNavigationItemDeleteUser(): User
{
    return User::query()->create([
        'name' => 'Delete User',
        'email' => 'nav-delete-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachNavigationItemDeleteMembership(User $user, array $permissions = AccountPermissionSeeder::PERMISSIONS): Account
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

function createNavigationItemDeleteWebsite(Account $account, string $prefix = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site',
        'subdomain' => $prefix.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

/**
 * @return array{0: Website, 1: Navigation, 2: NavigationVersion}
 */
function createNavigationItemDeleteNavigation(Account $account): array
{
    $website = createNavigationItemDeleteWebsite($account);
    $user = User::query()->find($account->owner_id);

    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);

    $draft = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
        'created_by' => $user?->id,
    ]);

    $navigation->assignDraftVersion($draft);

    return [$website, $navigation, $draft];
}

function createNavigationItemDeleteVersion(Navigation $navigation, User $user, int $version): NavigationVersion
{
    return NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => $version,
        'created_by' => $user->id,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createNavigationItemDeleteItem(NavigationVersion $version, array $overrides = []): NavigationItem
{
    return NavigationItem::query()->create(array_merge([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/item-'.uniqid(),
        'sort_order' => 0,
    ], $overrides));
}

test('unauthenticated navigation item delete is unauthorized', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);
    $item = createNavigationItemDeleteItem($draft, ['url' => '/x']);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $item))
        ->assertUnauthorized();
});

test('navigation item delete requires navigation update permission', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.view']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);
    $item = createNavigationItemDeleteItem($draft, ['url' => '/x']);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $item))
        ->assertForbidden();
});

test('navigation item delete rejects navigation delete without navigation update', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.delete']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);
    $item = createNavigationItemDeleteItem($draft, ['url' => '/x']);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $item))
        ->assertForbidden();
});

test('navigation item delete requires navigation update not page update', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['page.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);
    $item = createNavigationItemDeleteItem($draft, ['url' => '/x']);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $item))
        ->assertForbidden();
});

test('navigation item delete returns not found for wrong website nesting', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    $websiteA = createNavigationItemDeleteWebsite($account, 'a');
    $websiteB = createNavigationItemDeleteWebsite($account, 'b');
    [, $navigation, $draft] = createNavigationItemDeleteNavigation($account);
    $navigation->update(['website_id' => $websiteB->id]);
    $item = createNavigationItemDeleteItem($draft, ['url' => '/x']);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $websiteA, $navigation, $item))
        ->assertNotFound();
});

test('navigation item delete leaf removes only that item on unpublished draft', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a', 'sort_order' => 0]);
    $leaf = createNavigationItemDeleteItem($draft, ['url' => '/leaf', 'sort_order' => 5]);
    $c = createNavigationItemDeleteItem($draft, ['url' => '/c', 'sort_order' => 20]);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $leaf))
        ->assertNoContent();

    expect(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe(2)
        ->and($a->refresh()->sort_order)->toBe(0)
        ->and($c->refresh()->sort_order)->toBe(20)
        ->and(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1);
});

test('navigation item delete middle subtree removes node and descendants', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $c = createNavigationItemDeleteItem($draft, ['url' => '/c', 'parent_id' => $b->id]);
    $d = createNavigationItemDeleteItem($draft, ['url' => '/d', 'parent_id' => $a->id, 'sort_order' => 1]);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $b))
        ->assertNoContent();

    $remaining = NavigationItem::query()->where('navigation_version_id', $draft->id)->orderBy('url')->get();

    expect($remaining)->toHaveCount(2)
        ->and($remaining->pluck('url')->all())->toBe(['/a', '/d'])
        ->and($d->refresh()->parent_id)->toBe($a->id);
});

test('navigation item delete root subtree removes branching descendants only', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $c = createNavigationItemDeleteItem($draft, ['url' => '/c', 'parent_id' => $b->id]);
    $d = createNavigationItemDeleteItem($draft, ['url' => '/d', 'parent_id' => $b->id]);
    $e = createNavigationItemDeleteItem($draft, ['url' => '/e', 'parent_id' => $a->id]);
    $f = createNavigationItemDeleteItem($draft, ['url' => '/f', 'parent_id' => $e->id]);
    $other = createNavigationItemDeleteItem($draft, ['url' => '/other-root', 'sort_order' => 5]);
    $otherChild = createNavigationItemDeleteItem($draft, ['url' => '/other-child', 'parent_id' => $other->id]);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $a))
        ->assertNoContent();

    $urls = NavigationItem::query()
        ->where('navigation_version_id', $draft->id)
        ->orderBy('url')
        ->pluck('url')
        ->all();

    expect($urls)->toBe(['/other-child', '/other-root'])
        ->and($otherChild->refresh()->parent_id)->toBe($other->id);
});

test('navigation item delete deep subtree removes all descendants', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $c = createNavigationItemDeleteItem($draft, ['url' => '/c', 'parent_id' => $b->id]);
    $d = createNavigationItemDeleteItem($draft, ['url' => '/d', 'parent_id' => $c->id]);
    $e = createNavigationItemDeleteItem($draft, ['url' => '/e', 'parent_id' => $d->id]);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $a))
        ->assertNoContent();

    expect(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe(0);
});

test('published navigation item delete clones and preserves v1 subtree', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $navigation->assignPublishedVersion($draft);

    $v1ANumeric = $a->id;
    $v1BNumeric = $b->id;

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $b))
        ->assertNoContent();

    $navigation->refresh();
    $v2 = NavigationVersion::query()->where('navigation_id', $navigation->id)->where('version', 2)->firstOrFail();

    expect($navigation->published_version_id)->toBe($draft->id)
        ->and($navigation->draft_version_id)->toBe($v2->id)
        ->and(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe(2)
        ->and(NavigationItem::query()->find($v1BNumeric))->not->toBeNull()
        ->and(NavigationItem::query()->where('navigation_version_id', $v2->id)->count())->toBe(1)
        ->and(NavigationItem::query()->where('navigation_version_id', $v2->id)->wherePublicId($a->public_id)->exists())->toBeTrue()
        ->and(NavigationItem::query()->where('navigation_version_id', $v2->id)->wherePublicId($b->public_id)->exists())->toBeFalse()
        ->and(NavigationItem::query()->find($v1ANumeric)?->url)->toBe('/a');
});

test('published navigation middle subtree delete clones with expected v2 shape', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $c = createNavigationItemDeleteItem($draft, ['url' => '/c', 'parent_id' => $b->id]);
    $d = createNavigationItemDeleteItem($draft, ['url' => '/d', 'parent_id' => $a->id, 'sort_order' => 1]);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $b))
        ->assertNoContent();

    $v2 = NavigationVersion::query()->where('navigation_id', $navigation->id)->where('version', 2)->firstOrFail();
    $v2Urls = NavigationItem::query()->where('navigation_version_id', $v2->id)->orderBy('url')->pluck('url')->all();

    expect($v2Urls)->toBe(['/a', '/d'])
        ->and(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe(4);
});

test('ahead draft navigation item delete mutates v2 without creating v3', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    $actor = User::query()->findOrFail($account->owner_id);
    [$website, $navigation, $v1] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($v1, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($v1, ['url' => '/b', 'parent_id' => $a->id]);

    $v2 = createNavigationItemDeleteVersion($navigation, $actor, 2);
    $clonedA = createNavigationItemDeleteItem($v2, ['url' => '/a', 'public_id' => $a->public_id]);
    $clonedB = createNavigationItemDeleteItem($v2, ['url' => '/b', 'public_id' => $b->public_id, 'parent_id' => $clonedA->id]);

    $navigation->assignPublishedVersion($v1);
    $navigation->assignDraftVersion($v2);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $clonedB))
        ->assertNoContent();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2)
        ->and(NavigationItem::query()->where('navigation_version_id', $v2->id)->count())->toBe(1)
        ->and(NavigationItem::query()->where('navigation_version_id', $v1->id)->count())->toBe(2)
        ->and($b->refresh()->url)->toBe('/b');
});

test('navigation item delete returns not found for route identifier issues', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    $actor = User::query()->findOrFail($account->owner_id);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);
    $item = createNavigationItemDeleteItem($draft, ['url' => '/x']);

    $otherWebsite = createNavigationItemDeleteWebsite($account, 'other');
    $otherNavigation = Navigation::query()->create([
        'website_id' => $otherWebsite->id,
        'name' => 'Other',
        'key' => 'other-'.uniqid(),
    ]);
    $otherVersion = NavigationVersion::query()->create([
        'navigation_id' => $otherNavigation->id,
        'version' => 1,
        'created_by' => $user->id,
    ]);
    $foreign = createNavigationItemDeleteItem($otherVersion, ['url' => '/foreign']);

    $historical = createNavigationItemDeleteItem($draft, ['url' => '/historical']);
    $v2 = createNavigationItemDeleteVersion($navigation, $actor, 2);
    $navigation->assignPublishedVersion($draft);
    $navigation->assignDraftVersion($v2);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, 'not-a-uuid'))
        ->assertNotFound();

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, (string) Str::uuid()))
        ->assertNotFound();

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $foreign))
        ->assertNotFound();

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $historical))
        ->assertNotFound();

    $live = createNavigationItemDeleteItem($v2, ['url' => '/live']);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $live))
        ->assertNoContent();

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $live))
        ->assertNotFound();
});

test('navigation item delete on corrupt tree does not clone published navigation', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $a->update(['parent_id' => $b->id]);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $a))
        ->assertStatus(500);

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe(2);
});

test('navigation item delete rolls back clone when persistence fails', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemDeleteUser($user);

    NavigationItem::deleting(function (): void {
        throw new RuntimeException('Simulated item delete failure');
    });

    try {
        statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $b))
            ->assertStatus(500);
    } finally {
        NavigationItem::flushEventListeners();
    }

    $navigation->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and($navigation->draft_version_id)->toBe($draft->id)
        ->and($navigation->published_version_id)->toBe($draft->id)
        ->and(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe(2);
});

test('navigation item delete rolls back direct v1 delete when persistence fails', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $c = createNavigationItemDeleteItem($draft, ['url' => '/c', 'parent_id' => $b->id]);

    loginNavigationItemDeleteUser($user);

    $attempts = 0;

    NavigationItem::deleting(function () use (&$attempts): void {
        $attempts++;

        if ($attempts === 2) {
            throw new RuntimeException('Simulated item delete failure');
        }
    });

    try {
        statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $b))
            ->assertStatus(500);
    } finally {
        NavigationItem::flushEventListeners();
    }

    expect(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe(3);
});

test('get items after delete reads current draft without deleted subtree', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update', 'navigation.view']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $b))
        ->assertNoContent();

    statefulGetNavigationItemsForDelete(navigationItemsDeleteListUri($account, $website, $navigation))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $a->public_id);
});

test('reorder still works after navigation item delete with gapped sort orders', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a', 'sort_order' => 0]);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'sort_order' => 5]);
    $c = createNavigationItemDeleteItem($draft, ['url' => '/c', 'sort_order' => 20]);

    loginNavigationItemDeleteUser($user);

    statefulDeleteNavigationItem(navigationItemDeleteUri($account, $website, $navigation, $b))
        ->assertNoContent();

    $reorderUri = '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations/'.$navigation->id.'/items/order';

    statefulPutNavigationItemReorderForDelete($reorderUri, [
        'parent_id' => null,
        'item_ids' => [$c->public_id, $a->public_id],
    ])->assertNoContent();

    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1);
});

test('navigation item deleter resolves subtree by public id after clone', function () {
    $user = createNavigationItemDeleteUser();
    $account = attachNavigationItemDeleteMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemDeleteNavigation($account);

    $a = createNavigationItemDeleteItem($draft, ['url' => '/a']);
    $b = createNavigationItemDeleteItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $navigation->assignPublishedVersion($draft);

    $v1BId = $b->id;

    app(NavigationItemDeleter::class)->delete($navigation, $user, $b->public_id);

    $v2 = NavigationVersion::query()->where('navigation_id', $navigation->id)->where('version', 2)->firstOrFail();

    expect(NavigationItem::query()->find($v1BId))->not->toBeNull()
        ->and(NavigationItem::query()->where('navigation_version_id', $v2->id)->wherePublicId($b->public_id)->exists())->toBeFalse();
});
