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
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function navigationItemReorderUri(Account $account, Website $website, Navigation $navigation): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations/'.$navigation->id.'/items/order';
}

function navigationItemReorderOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncNavigationItemReorderCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPutNavigationItemReorder(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(navigationItemReorderOriginHeaders())->putJson($uri, $data);

    syncNavigationItemReorderCookies($response);

    return $response;
}

function loginNavigationItemReorderUser(User $user): void
{
    test()->withHeaders(navigationItemReorderOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createNavigationItemReorderUser(): User
{
    return User::query()->create([
        'name' => 'Reorder User',
        'email' => 'nav-reorder-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachNavigationItemReorderMembership(User $user, array $permissions = AccountPermissionSeeder::PERMISSIONS): Account
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

function createNavigationItemReorderWebsite(Account $account, string $prefix = 'site'): Website
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
function createNavigationItemReorderNavigation(Account $account): array
{
    $website = createNavigationItemReorderWebsite($account);
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

function createNavigationItemReorderVersion(Navigation $navigation, User $user, int $version): NavigationVersion
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
function createNavigationItemReorderItem(NavigationVersion $version, array $overrides = []): NavigationItem
{
    return NavigationItem::query()->create(array_merge([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/item-'.uniqid(),
        'sort_order' => 0,
    ], $overrides));
}

test('unauthenticated navigation item reorder is unauthorized', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user);
    [$website, $navigation] = array_slice(createNavigationItemReorderNavigation($account), 0, 2);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [],
    ])->assertUnauthorized();
});

test('navigation item reorder requires navigation update permission', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.view']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);
    $a = createNavigationItemReorderItem($draft, ['url' => '/a']);

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [$a->public_id],
    ])->assertForbidden();
});

test('navigation item reorder requires navigation update not page update', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['page.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);
    $a = createNavigationItemReorderItem($draft, ['url' => '/a']);

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [$a->public_id],
    ])->assertForbidden();
});

test('navigation item reorder returns not found for wrong website nesting', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    $websiteA = createNavigationItemReorderWebsite($account, 'a');
    $websiteB = createNavigationItemReorderWebsite($account, 'b');
    [, $navigationOnB] = array_slice(createNavigationItemReorderNavigation($account), 0, 2);
    $navigationOnB->update(['website_id' => $websiteB->id]);

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $websiteA, $navigationOnB), [
        'parent_id' => null,
        'item_ids' => [],
    ])->assertNotFound();
});

test('navigation item reorder validates request structure', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);
    $a = createNavigationItemReorderItem($draft, ['url' => '/a']);
    $b = createNavigationItemReorderItem($draft, ['url' => '/b', 'sort_order' => 1]);

    loginNavigationItemReorderUser($user);

    $uri = navigationItemReorderUri($account, $website, $navigation);

    statefulPutNavigationItemReorder($uri, [
        'item_ids' => [$a->public_id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => null,
    ])->assertUnprocessable()->assertJsonValidationErrors(['item_ids']);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => null,
        'item_ids' => [$a->public_id, $a->public_id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['item_ids']);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => 'not-a-uuid',
        'item_ids' => [$a->public_id, $b->public_id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => null,
        'item_ids' => ['not-a-uuid'],
        'extra' => true,
    ])->assertUnprocessable();
});

test('navigation item reorder root siblings applies dense order on unpublished draft', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    $a = createNavigationItemReorderItem($draft, ['url' => '/a', 'sort_order' => 0]);
    $b = createNavigationItemReorderItem($draft, ['url' => '/b', 'sort_order' => 1]);
    $c = createNavigationItemReorderItem($draft, ['url' => '/c', 'sort_order' => 2]);
    $parent = createNavigationItemReorderItem($draft, ['url' => '/p', 'sort_order' => 3]);
    $child = createNavigationItemReorderItem($draft, [
        'url' => '/child',
        'parent_id' => $parent->id,
        'sort_order' => 0,
    ]);

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [$c->public_id, $a->public_id, $b->public_id, $parent->public_id],
    ])->assertNoContent();

    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($b->refresh()->sort_order)->toBe(2)
        ->and($parent->refresh()->sort_order)->toBe(3)
        ->and($child->refresh()->sort_order)->toBe(0)
        ->and($child->parent_id)->toBe($parent->id);
});

test('navigation item reorder child collection preserves parent and grandchildren', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    $p = createNavigationItemReorderItem($draft, ['url' => '/p', 'sort_order' => 0]);
    $a = createNavigationItemReorderItem($draft, ['url' => '/a', 'parent_id' => $p->id, 'sort_order' => 0]);
    $b = createNavigationItemReorderItem($draft, ['url' => '/b', 'parent_id' => $p->id, 'sort_order' => 1]);
    $c = createNavigationItemReorderItem($draft, ['url' => '/c', 'parent_id' => $p->id, 'sort_order' => 2]);
    $x = createNavigationItemReorderItem($draft, ['url' => '/x', 'parent_id' => $a->id, 'sort_order' => 0]);
    $otherParent = createNavigationItemReorderItem($draft, ['url' => '/other', 'sort_order' => 5]);
    $otherChild = createNavigationItemReorderItem($draft, [
        'url' => '/other-child',
        'parent_id' => $otherParent->id,
        'sort_order' => 0,
    ]);

    $pParentBefore = $p->parent_id;
    $otherChildOrderBefore = $otherChild->sort_order;

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => $p->public_id,
        'item_ids' => [$c->public_id, $a->public_id, $b->public_id],
    ])->assertNoContent();

    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($b->refresh()->sort_order)->toBe(2)
        ->and($a->parent_id)->toBe($p->id)
        ->and($b->parent_id)->toBe($p->id)
        ->and($c->parent_id)->toBe($p->id)
        ->and($p->refresh()->parent_id)->toBe($pParentBefore)
        ->and($x->refresh()->parent_id)->toBe($a->id)
        ->and($otherChild->refresh()->sort_order)->toBe($otherChildOrderBefore);
});

test('navigation item reorder rejects invalid sibling membership', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    $a = createNavigationItemReorderItem($draft, ['url' => '/a', 'sort_order' => 0]);
    $b = createNavigationItemReorderItem($draft, ['url' => '/b', 'sort_order' => 1]);
    $child = createNavigationItemReorderItem($draft, ['url' => '/c', 'parent_id' => $a->id, 'sort_order' => 0]);

    $otherWebsite = createNavigationItemReorderWebsite($account, 'other');
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
    $foreign = createNavigationItemReorderItem($otherVersion, ['url' => '/foreign']);

    loginNavigationItemReorderUser($user);

    $uri = navigationItemReorderUri($account, $website, $navigation);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => null,
        'item_ids' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['item_ids']);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => null,
        'item_ids' => [$a->public_id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['item_ids']);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => null,
        'item_ids' => [$a->public_id, $b->public_id, $foreign->public_id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['item_ids']);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => null,
        'item_ids' => [$a->public_id, $b->public_id, $child->public_id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['item_ids']);
});

test('navigation item reorder rejects unknown and historical parent', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    $actor = User::query()->findOrFail($account->owner_id);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    createNavigationItemReorderItem($draft, ['url' => '/a']);

    loginNavigationItemReorderUser($user);

    $uri = navigationItemReorderUri($account, $website, $navigation);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => (string) Str::uuid(),
        'item_ids' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);

    $historicalParent = createNavigationItemReorderItem($draft, ['url' => '/historical-parent']);
    $v2 = createNavigationItemReorderVersion($navigation, $actor, 2);
    $navigation->assignPublishedVersion($draft);
    $navigation->assignDraftVersion($v2);

    statefulPutNavigationItemReorder($uri, [
        'parent_id' => $historicalParent->public_id,
        'item_ids' => [],
    ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);
});

test('navigation item reorder rejects historical only item ids on ahead draft', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    $actor = User::query()->findOrFail($account->owner_id);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    $historical = createNavigationItemReorderItem($draft, ['url' => '/historical']);
    $v2 = createNavigationItemReorderVersion($navigation, $actor, 2);
    $navigation->assignPublishedVersion($draft);
    $navigation->assignDraftVersion($v2);

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [$historical->public_id],
    ])->assertUnprocessable()->assertJsonValidationErrors(['item_ids']);
});

test('navigation item reorder gapped identical order is semantic no-op', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    $a = createNavigationItemReorderItem($draft, ['url' => '/a', 'sort_order' => 0]);
    $b = createNavigationItemReorderItem($draft, ['url' => '/b', 'sort_order' => 5]);
    $c = createNavigationItemReorderItem($draft, ['url' => '/c', 'sort_order' => 20]);

    $navigation->assignPublishedVersion($draft);
    $navigationUpdatedAt = $navigation->updated_at;
    $aUpdatedAt = $a->updated_at;

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [$a->public_id, $b->public_id, $c->public_id],
    ])->assertNoContent();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and($a->refresh()->sort_order)->toBe(0)
        ->and($b->refresh()->sort_order)->toBe(5)
        ->and($c->refresh()->sort_order)->toBe(20)
        ->and($a->updated_at->eq($aUpdatedAt))->toBeTrue()
        ->and($navigation->refresh()->updated_at->eq($navigationUpdatedAt))->toBeTrue();
});

test('navigation item reorder uses sort order then id for effective ordering', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    $first = createNavigationItemReorderItem($draft, ['url' => '/first', 'sort_order' => 0]);
    $second = createNavigationItemReorderItem($draft, ['url' => '/second', 'sort_order' => 0]);

    $effective = NavigationItem::query()
        ->where('navigation_version_id', $draft->id)
        ->whereNull('parent_id')
        ->orderBy('sort_order')
        ->orderBy('id')
        ->pluck('public_id')
        ->all();

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => $effective,
    ])->assertNoContent();

    expect($first->refresh()->sort_order)->toBe(0)
        ->and($second->refresh()->sort_order)->toBe(0);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [$second->public_id, $first->public_id],
    ])->assertNoContent();

    expect($second->refresh()->sort_order)->toBe(0)
        ->and($first->refresh()->sort_order)->toBe(1);
});

test('navigation item reorder empty collection is semantic no-op', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation] = array_slice(createNavigationItemReorderNavigation($account), 0, 2);

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [],
    ])->assertNoContent();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1);
});

test('navigation item reorder single item identical order is semantic no-op', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    $only = createNavigationItemReorderItem($draft, ['url' => '/only', 'sort_order' => 17]);

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [$only->public_id],
    ])->assertNoContent();

    expect($only->refresh()->sort_order)->toBe(17)
        ->and(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1);
});

test('published navigation meaningful reorder clones and leaves v1 ordering unchanged', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    $a = createNavigationItemReorderItem($draft, ['url' => '/a', 'sort_order' => 0]);
    $b = createNavigationItemReorderItem($draft, ['url' => '/b', 'sort_order' => 1]);
    $navigation->assignPublishedVersion($draft);

    $aNumeric = $a->id;
    $bNumeric = $b->id;

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [$b->public_id, $a->public_id],
    ])->assertNoContent();

    $navigation->refresh();
    $v2 = NavigationVersion::query()->where('navigation_id', $navigation->id)->where('version', 2)->firstOrFail();

    $clonedA = NavigationItem::query()->where('navigation_version_id', $v2->id)->wherePublicId($a->public_id)->firstOrFail();
    $clonedB = NavigationItem::query()->where('navigation_version_id', $v2->id)->wherePublicId($b->public_id)->firstOrFail();

    expect($navigation->published_version_id)->toBe($draft->id)
        ->and($navigation->draft_version_id)->toBe($v2->id)
        ->and($clonedB->sort_order)->toBe(0)
        ->and($clonedA->sort_order)->toBe(1)
        ->and($clonedB->id)->not->toBe($bNumeric)
        ->and(NavigationItem::query()->find($aNumeric)?->sort_order)->toBe(0)
        ->and(NavigationItem::query()->find($bNumeric)?->sort_order)->toBe(1);
});

test('ahead draft reorder mutates v2 without creating v3', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    $actor = User::query()->findOrFail($account->owner_id);
    [$website, $navigation, $v1] = createNavigationItemReorderNavigation($account);

    $a = createNavigationItemReorderItem($v1, ['url' => '/a', 'sort_order' => 0]);
    $b = createNavigationItemReorderItem($v1, ['url' => '/b', 'sort_order' => 1]);

    $v2 = createNavigationItemReorderVersion($navigation, $actor, 2);
    $clonedA = createNavigationItemReorderItem($v2, [
        'url' => '/a',
        'sort_order' => 0,
        'public_id' => $a->public_id,
    ]);
    $clonedB = createNavigationItemReorderItem($v2, [
        'url' => '/b',
        'sort_order' => 1,
        'public_id' => $b->public_id,
    ]);

    $navigation->assignPublishedVersion($v1);
    $navigation->assignDraftVersion($v2);

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [$clonedB->public_id, $clonedA->public_id],
    ])->assertNoContent();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2)
        ->and($clonedB->refresh()->sort_order)->toBe(0)
        ->and($clonedA->refresh()->sort_order)->toBe(1)
        ->and($a->refresh()->sort_order)->toBe(0);
});

test('invalid membership on published navigation does not clone', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    createNavigationItemReorderItem($draft, ['url' => '/a']);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemReorderUser($user);

    statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
        'parent_id' => null,
        'item_ids' => [(string) Str::uuid()],
    ])->assertUnprocessable();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1);
});

test('navigation item reorder rolls back clone when persistence fails', function () {
    $user = createNavigationItemReorderUser();
    $account = attachNavigationItemReorderMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemReorderNavigation($account);

    $a = createNavigationItemReorderItem($draft, ['url' => '/a', 'sort_order' => 0]);
    $b = createNavigationItemReorderItem($draft, ['url' => '/b', 'sort_order' => 1]);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemReorderUser($user);

    NavigationItem::updating(function (): void {
        throw new RuntimeException('Simulated reorder persistence failure');
    });

    try {
        statefulPutNavigationItemReorder(navigationItemReorderUri($account, $website, $navigation), [
            'parent_id' => null,
            'item_ids' => [$b->public_id, $a->public_id],
        ])->assertStatus(500);
    } finally {
        NavigationItem::flushEventListeners();
    }

    $navigation->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and($navigation->draft_version_id)->toBe($draft->id)
        ->and($navigation->published_version_id)->toBe($draft->id)
        ->and($a->refresh()->sort_order)->toBe(0)
        ->and($b->refresh()->sort_order)->toBe(1);
});
