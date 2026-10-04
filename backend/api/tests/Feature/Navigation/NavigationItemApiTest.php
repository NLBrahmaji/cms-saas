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
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

test('unauthenticated navigation item requests are unauthorized', function () {
    [$account, $website, $navigation] = createNavigationItemApiFixture();

    statefulGetJsonForNavigationItems(navigationItemsUri($account, $website, $navigation))->assertUnauthorized();
    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'url',
        'url' => '/about',
    ])->assertUnauthorized();

    $draft = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $item = createNavigationItemApiItem($draft, ['url' => '/patch-target']);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'label' => 'Nope',
    ])->assertUnauthorized();
});

test('navigation item list requires navigation view not page view', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['page.view']);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);

    loginNavigationItemApiUser($user);

    statefulGetJsonForNavigationItems(navigationItemsUri($account, $website, $navigation))->assertForbidden();
});

test('navigation item create requires navigation update not page update', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['page.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
    ])->assertForbidden();
});

test('navigation item list returns empty draft collection', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.view']);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);

    loginNavigationItemApiUser($user);

    statefulGetJsonForNavigationItems(navigationItemsUri($account, $website, $navigation))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('navigation item list returns flat draft items with parent public ids', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.view']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);

    $parent = createNavigationItemApiItem($draft, ['url' => '/parent', 'sort_order' => 0]);
    $child = createNavigationItemApiItem($draft, [
        'parent_id' => $parent->id,
        'url' => '/child',
        'sort_order' => 5,
    ]);

    loginNavigationItemApiUser($user);

    statefulGetJsonForNavigationItems(navigationItemsUri($account, $website, $navigation))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                navigationItemResourcePayload($parent),
                navigationItemResourcePayload($child),
            ],
        ]);
});

test('navigation item list reads draft not published version after ahead draft exists', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.view']);
    $user = User::query()->findOrFail($account->owner_id);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);

    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    createNavigationItemApiItem($v1, ['url' => '/published-only']);

    $v2 = createNavigationItemApiVersion($navigation, $user, 2);
    createNavigationItemApiItem($v2, ['url' => '/draft']);

    $navigation->assignPublishedVersion($v1);
    $navigation->assignDraftVersion($v2);

    loginNavigationItemApiUser($user);

    statefulGetJsonForNavigationItems(navigationItemsUri($account, $website, $navigation))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.url', '/draft');
});

test('navigation item create appends root page item on unpublished draft', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
        'label' => 'About',
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'page')
        ->assertJsonPath('data.page_id', $page->id)
        ->assertJsonPath('data.url', null)
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.sort_order', 0)
        ->assertJsonPath('data.open_in_new_tab', false);

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1);
});

test('navigation item create clones published canonical snapshot before adding item', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $existing = createNavigationItemApiItem($v1, ['url' => '/home', 'sort_order' => 0]);
    $navigation->assignPublishedVersion($v1);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
    ])->assertCreated();

    $navigation->refresh();

    expect($navigation->published_version_id)->toBe($v1->id)
        ->and(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2);

    $v2 = NavigationVersion::query()->where('navigation_id', $navigation->id)->where('version', 2)->firstOrFail();

    expect($navigation->draft_version_id)->toBe($v2->id)
        ->and(NavigationItem::query()->where('navigation_version_id', $v1->id)->count())->toBe(1)
        ->and(NavigationItem::query()->where('navigation_version_id', $v2->id)->count())->toBe(2)
        ->and(NavigationItem::query()->where('navigation_version_id', $v2->id)->wherePublicId($existing->public_id)->exists())->toBeTrue();
});

test('navigation item create re-resolves nested parent after published clone', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $parent = createNavigationItemApiItem($v1, ['url' => '/parent', 'sort_order' => 0]);
    $navigation->assignPublishedVersion($v1);

    loginNavigationItemApiUser($user);

    $response = statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
        'parent_id' => $parent->public_id,
    ])->assertCreated();

    $v2 = NavigationVersion::query()->where('navigation_id', $navigation->id)->where('version', 2)->firstOrFail();
    $clonedParent = NavigationItem::query()
        ->where('navigation_version_id', $v2->id)
        ->wherePublicId($parent->public_id)
        ->firstOrFail();

    $child = NavigationItem::query()
        ->where('navigation_version_id', $v2->id)
        ->wherePublicId($response->json('data.id'))
        ->firstOrFail();

    expect($child->parent_id)->toBe($clonedParent->id)
        ->and($child->parent_id)->not->toBe($parent->id)
        ->and($clonedParent->navigation_version_id)->toBe($v2->id);
});

test('navigation item create appends within sibling group preserving gaps', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);

    createNavigationItemApiItem($draft, ['url' => '/a', 'sort_order' => 0]);
    createNavigationItemApiItem($draft, ['url' => '/b', 'sort_order' => 5]);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'url',
        'url' => '/c',
    ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 6);
});

test('navigation item create on ahead draft does not create another version', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);
    $actor = User::query()->findOrFail($account->owner_id);

    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $v2 = createNavigationItemApiVersion($navigation, $actor, 2);
    $navigation->assignPublishedVersion($v1);
    $navigation->assignDraftVersion($v2);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
    ])->assertCreated();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2);
});

test('navigation item post then get returns created item from draft', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.view', 'navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    loginNavigationItemApiUser($user);

    $create = statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
        'label' => 'About',
    ])->assertCreated();

    statefulGetJsonForNavigationItems(navigationItemsUri($account, $website, $navigation))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $create->json('data.id'))
        ->assertJsonPath('data.0.label', 'About');
});

test('invalid parent on published navigation returns 422 without cloning', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $navigation->assignPublishedVersion($v1);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
        'parent_id' => (string) Str::uuid(),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1);
});

test('historical parent public id not in current draft is rejected', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $historicalParent = createNavigationItemApiItem($v1, ['url' => '/old-parent']);
    $v2 = createNavigationItemApiVersion($navigation, $user, 2);

    $navigation->assignPublishedVersion($v1);
    $navigation->assignDraftVersion($v2);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
        'parent_id' => $historicalParent->public_id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

test('navigation item create rejects soft deleted page and foreign website page', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);
    $otherWebsite = createNavigationItemApiWebsite($account);
    $foreignPage = Page::query()->create(['website_id' => $otherWebsite->id]);
    $deletedPage = Page::query()->create(['website_id' => $website->id]);
    $deletedPage->delete();

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $foreignPage->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['page_id']);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $deletedPage->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['page_id']);
});

test('navigation item create allows unpublished page target', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
    ])->assertCreated();
});

test('navigation item url validation accepts relative and absolute http urls', function (string $url) {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'url',
        'url' => $url,
    ])->assertCreated();
})->with([
    '/contact',
    '/about/team',
    '/products?category=1',
    '/contact#form',
    'https://example.com',
    'http://example.com/path',
]);

test('navigation item url validation rejects invalid targets', function (string $url) {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'url',
        'url' => $url,
    ])->assertUnprocessable()->assertJsonValidationErrors(['url']);
})->with([
    'javascript:alert(1)',
    'data:text/html,hello',
    'file:///tmp/test',
    'mailto:test@example.com',
    'tel:+123',
    'example.com',
    'contact',
    '//example.com',
]);

test('navigation item create rejects incompatible and unknown fields', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
        'url' => '/nope',
    ])->assertUnprocessable()->assertJsonValidationErrors(['url']);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'url',
        'url' => '/about',
        'page_id' => $page->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['page_id']);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'page',
        'page_id' => $page->id,
        'sort_order' => 99,
    ])->assertUnprocessable()->assertJsonValidationErrors(['sort_order']);
});

test('navigation item create enforces strict open in new tab boolean', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);

    loginNavigationItemApiUser($user);

    statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
        'type' => 'url',
        'url' => '/about',
        'open_in_new_tab' => 'true',
    ])->assertUnprocessable()->assertJsonValidationErrors(['open_in_new_tab']);
});

test('navigation item create rolls back clone when item persistence fails', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);

    $v1 = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $navigation->assignPublishedVersion($v1);

    NavigationItem::creating(function (): void {
        throw new RuntimeException('Simulated item persistence failure');
    });

    loginNavigationItemApiUser($user);

    try {
        statefulPostJsonForNavigationItems(navigationItemsUri($account, $website, $navigation), [
            'type' => 'page',
            'page_id' => $page->id,
        ])->assertStatus(500);
    } finally {
        NavigationItem::flushEventListeners();
    }

    $navigation->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and(NavigationItem::query()->count())->toBe(0)
        ->and($navigation->draft_version_id)->toBe($v1->id)
        ->and($navigation->published_version_id)->toBe($v1->id);
});

test('navigation item routes return not found for wrong website nesting', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.view', 'navigation.update']);
    $websiteA = createNavigationItemApiWebsite($account, 'a');
    $websiteB = createNavigationItemApiWebsite($account, 'b');
    [, $navigationOnB] = createNavigationItemApiWebsiteAndNavigation($account, $websiteB);

    loginNavigationItemApiUser($user);

    statefulGetJsonForNavigationItems(navigationItemsUri($account, $websiteA, $navigationOnB))->assertNotFound();
});

function navigationItemsUri(Account $account, Website $website, Navigation $navigation): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations/'.$navigation->id.'/items';
}

function navigationItemResourcePayload(NavigationItem $item): array
{
    $item->loadMissing('parent');

    return [
        'id' => $item->public_id,
        'type' => $item->type->value,
        'label' => $item->label,
        'page_id' => $item->page_id,
        'url' => $item->url,
        'parent_id' => $item->parent?->public_id,
        'sort_order' => $item->sort_order,
        'open_in_new_tab' => (bool) $item->open_in_new_tab,
    ];
}

function createNavigationItemApiFixture(): array
{
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, AccountPermissionSeeder::PERMISSIONS);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);

    return [$account, $website, $navigation];
}

function createNavigationItemApiUser(): User
{
    return User::query()->create([
        'name' => 'Item API User',
        'email' => 'nav-item-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function loginNavigationItemApiUser(User $user): void
{
    statefulPostJsonForNavigationItems('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachNavigationItemApiMembership(User $user, array $permissions): Account
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

function createNavigationItemApiWebsite(Account $account, string $prefix = 'site'): Website
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
 * @return array{0: Website, 1: Navigation}
 */
function createNavigationItemApiWebsiteAndNavigation(Account $account, ?Website $website = null): array
{
    $website ??= createNavigationItemApiWebsite($account);
    $user = User::query()->find($account->owner_id);

    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);

    $version = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
        'created_by' => $user?->id,
    ]);

    $navigation->assignDraftVersion($version);

    return [$website, $navigation];
}

/**
 * @return array{0: Website, 1: Navigation, 2: NavigationVersion}
 */
function createNavigationItemApiWebsiteNavigationAndDraft(Account $account): array
{
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);
    $draft = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();

    return [$website, $navigation, $draft];
}

/**
 * @return array{0: Website, 1: Navigation, 2: Page}
 */
function createNavigationItemApiWebsiteNavigationAndPage(Account $account): array
{
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);
    $user = User::query()->find($account->owner_id);
    $page = Page::query()->create(['website_id' => $website->id]);

    PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'About',
        'slug' => 'about',
        'created_by' => $user?->id,
    ]);

    return [$website, $navigation, $page];
}

function createNavigationItemApiVersion(Navigation $navigation, User $user, int $version): NavigationVersion
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
function createNavigationItemApiItem(NavigationVersion $version, array $overrides = []): NavigationItem
{
    return NavigationItem::query()->create(array_merge([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/item-'.uniqid(),
        'sort_order' => 0,
    ], $overrides));
}

function navigationItemApiOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncNavigationItemApiCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetJsonForNavigationItems(string $uri): TestResponse
{
    $response = test()->withHeaders(navigationItemApiOriginHeaders())->getJson($uri);

    syncNavigationItemApiCookies($response);

    return $response;
}

function statefulPostJsonForNavigationItems(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(navigationItemApiOriginHeaders())->postJson($uri, $data);

    syncNavigationItemApiCookies($response);

    return $response;
}

function statefulPatchNavigationItem(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(navigationItemApiOriginHeaders())->patchJson($uri, $data);

    syncNavigationItemApiCookies($response);

    return $response;
}

function navigationItemUri(
    Account $account,
    Website $website,
    Navigation $navigation,
    NavigationItem|string $item,
): string {
    $itemId = $item instanceof NavigationItem ? $item->public_id : $item;

    return navigationItemsUri($account, $website, $navigation).'/'.$itemId;
}

test('navigation item patch requires navigation update permission', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.view']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/about']);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'label' => 'Nope',
    ])->assertForbidden();
});

test('navigation item patch updates label on unpublished draft', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/about', 'label' => 'Old']);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'label' => 'New',
    ])
        ->assertOk()
        ->assertJsonPath('data.label', 'New');

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1);
});

test('navigation item patch no-op on canonical published navigation does not clone', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/about', 'label' => 'About']);

    $navigation->assignPublishedVersion($draft);

    loginNavigationItemApiUser($user);

    $updatedAt = $item->updated_at;

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'label' => 'About',
    ])->assertOk();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and($item->refresh()->updated_at->eq($updatedAt))->toBeTrue();
});

test('navigation item patch clones canonical published navigation for meaningful change', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/about', 'sort_order' => 3, 'label' => 'About']);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'label' => 'Updated',
    ])->assertOk();

    $navigation->refresh();
    $v2 = NavigationVersion::query()->where('navigation_id', $navigation->id)->where('version', 2)->firstOrFail();
    $v1Item = NavigationItem::query()->where('navigation_version_id', $draft->id)->wherePublicId($item->public_id)->firstOrFail();
    $v2Item = NavigationItem::query()->where('navigation_version_id', $v2->id)->wherePublicId($item->public_id)->firstOrFail();

    expect($navigation->published_version_id)->toBe($draft->id)
        ->and($navigation->draft_version_id)->toBe($v2->id)
        ->and($v1Item->label)->toBe('About')
        ->and($v2Item->label)->toBe('Updated')
        ->and($v2Item->sort_order)->toBe(3)
        ->and($v1Item->id)->not->toBe($v2Item->id);
});

test('navigation item patch transitions page to url and clears page id', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);
    $draft = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $item = createNavigationItemApiItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'url' => null,
    ]);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'type' => 'url',
        'url' => '/team',
    ])
        ->assertOk()
        ->assertJsonPath('data.type', 'url')
        ->assertJsonPath('data.url', '/team')
        ->assertJsonPath('data.page_id', null);

    expect($item->refresh()->page_id)->toBeNull()
        ->and($item->url)->toBe('/team');
});

test('navigation item patch rejects contradictory type transition payload', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);
    $draft = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $item = createNavigationItemApiItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'url' => null,
    ]);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'type' => 'url',
        'url' => '/team',
        'page_id' => $page->id,
    ])->assertUnprocessable();
});

test('navigation item patch move under parent appends sort order in destination siblings', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);

    $rootA = createNavigationItemApiItem($draft, ['url' => '/a', 'sort_order' => 0]);
    $moving = createNavigationItemApiItem($draft, ['url' => '/b', 'sort_order' => 5]);
    $parent = createNavigationItemApiItem($draft, ['url' => '/p', 'sort_order' => 10]);
    createNavigationItemApiItem($draft, ['url' => '/c', 'parent_id' => $parent->id, 'sort_order' => 2]);
    createNavigationItemApiItem($draft, ['url' => '/d', 'parent_id' => $parent->id, 'sort_order' => 8]);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $moving), [
        'parent_id' => $parent->public_id,
    ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 9);

    expect($rootA->refresh()->sort_order)->toBe(0);
});

test('navigation item patch rejects moving item under its descendant', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);

    $a = createNavigationItemApiItem($draft, ['url' => '/a']);
    $b = createNavigationItemApiItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $c = createNavigationItemApiItem($draft, ['url' => '/c', 'parent_id' => $b->id]);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $a), [
        'parent_id' => $c->public_id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['parent_id']);
});

test('navigation item patch re-resolves parent after published clone', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);

    $parent = createNavigationItemApiItem($draft, ['url' => '/parent']);
    $child = createNavigationItemApiItem($draft, ['url' => '/child', 'parent_id' => $parent->id]);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $child), [
        'label' => 'Child updated',
    ])->assertOk();

    $v2 = NavigationVersion::query()->where('navigation_id', $navigation->id)->where('version', 2)->firstOrFail();
    $clonedParent = NavigationItem::query()->where('navigation_version_id', $v2->id)->wherePublicId($parent->public_id)->firstOrFail();
    $clonedChild = NavigationItem::query()->where('navigation_version_id', $v2->id)->wherePublicId($child->public_id)->firstOrFail();

    expect($clonedChild->parent_id)->toBe($clonedParent->id)
        ->and($clonedChild->parent_id)->not->toBe($parent->id);
});

test('navigation item patch returns not found for unknown route item id', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, (string) Str::uuid()), [
        'label' => 'Ghost',
    ])->assertNotFound();
});

test('invalid parent on published navigation patch does not clone', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/item']);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'parent_id' => (string) Str::uuid(),
    ])->assertUnprocessable();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1);
});

test('navigation item patch on ahead draft mutates v2 without creating v3', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    $actor = User::query()->findOrFail($account->owner_id);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);

    $v1 = $draft;
    $v2 = createNavigationItemApiVersion($navigation, $actor, 2);
    $item = createNavigationItemApiItem($v2, ['url' => '/draft-item', 'label' => 'Draft']);
    $navigation->assignPublishedVersion($v1);
    $navigation->assignDraftVersion($v2);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'label' => 'Changed on v2',
    ])
        ->assertOk()
        ->assertJsonPath('data.label', 'Changed on v2');

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2)
        ->and($item->refresh()->label)->toBe('Changed on v2');
});

test('navigation item patch move preserves subtree structure', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);

    $d = createNavigationItemApiItem($draft, ['url' => '/d']);
    $b = createNavigationItemApiItem($draft, ['url' => '/b']);
    $c = createNavigationItemApiItem($draft, ['url' => '/c', 'parent_id' => $b->id]);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $b), [
        'parent_id' => $d->public_id,
    ])->assertOk();

    expect($c->refresh()->parent_id)->toBe($b->id)
        ->and($b->refresh()->parent_id)->toBe($d->id);
});

test('navigation item patch move nested item to root appends among root siblings', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);

    createNavigationItemApiItem($draft, ['url' => '/r0', 'sort_order' => 0]);
    createNavigationItemApiItem($draft, ['url' => '/r5', 'sort_order' => 5]);
    createNavigationItemApiItem($draft, ['url' => '/r20', 'sort_order' => 20]);
    $parent = createNavigationItemApiItem($draft, ['url' => '/p', 'sort_order' => 25]);
    $child = createNavigationItemApiItem($draft, ['url' => '/child', 'parent_id' => $parent->id, 'sort_order' => 1]);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $child), [
        'parent_id' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.sort_order', 26)
        ->assertJsonPath('data.parent_id', null);
});

test('navigation item patch clears label with null', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/x', 'label' => 'Visible']);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'label' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.label', null);

    expect($item->refresh()->label)->toBeNull();
});

test('navigation item patch transitions url to page and clears url', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);
    $draft = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $item = createNavigationItemApiItem($draft, ['url' => '/old']);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'type' => 'page',
        'page_id' => $page->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.type', 'page')
        ->assertJsonPath('data.page_id', $page->id)
        ->assertJsonPath('data.url', null);

    expect($item->refresh()->url)->toBeNull()
        ->and($item->page_id)->toBe($page->id);
});

test('navigation item patch rejects page from another website', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $otherWebsite = createNavigationItemApiWebsite($account, 'other');
    $otherPage = Page::query()->create(['website_id' => $otherWebsite->id]);
    $item = createNavigationItemApiItem($draft, ['url' => '/x']);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'type' => 'page',
        'page_id' => $otherPage->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['page_id']);
});

test('navigation item patch rejects soft deleted page target', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $page] = createNavigationItemApiWebsiteNavigationAndPage($account);
    $draft = NavigationVersion::query()->where('navigation_id', $navigation->id)->firstOrFail();
    $page->delete();
    $item = createNavigationItemApiItem($draft, ['url' => '/x']);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'type' => 'page',
        'page_id' => $page->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['page_id']);
});

test('navigation item patch rejects empty body', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/x']);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [])
        ->assertUnprocessable();
});

test('navigation item patch enforces strict open in new tab boolean', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/x']);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'open_in_new_tab' => 'true',
    ])->assertUnprocessable()->assertJsonValidationErrors(['open_in_new_tab']);
});

test('navigation item patch returns not found for malformed route item id', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation] = createNavigationItemApiWebsiteAndNavigation($account);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, 'not-a-uuid'), [
        'label' => 'X',
    ])->assertNotFound();
});

test('navigation item patch returns not found for historical only item public id', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    $actor = User::query()->findOrFail($account->owner_id);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);

    $historical = createNavigationItemApiItem($draft, ['url' => '/historical']);
    $v2 = createNavigationItemApiVersion($navigation, $actor, 2);
    $navigation->assignPublishedVersion($draft);
    $navigation->assignDraftVersion($v2);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $historical), [
        'label' => 'Ghost',
    ])->assertNotFound();
});

test('navigation item patch rolls back clone when item persistence fails', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['navigation.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/item', 'label' => 'Before']);
    $navigation->assignPublishedVersion($draft);

    loginNavigationItemApiUser($user);

    NavigationItem::updating(function (): void {
        throw new RuntimeException('Simulated item persistence failure');
    });

    try {
        statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
            'label' => 'After',
        ])->assertStatus(500);
    } finally {
        NavigationItem::flushEventListeners();
    }

    $navigation->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and($navigation->draft_version_id)->toBe($draft->id)
        ->and($navigation->published_version_id)->toBe($draft->id)
        ->and($item->refresh()->label)->toBe('Before');
});

test('navigation item patch requires navigation update not page update', function () {
    $user = createNavigationItemApiUser();
    $account = attachNavigationItemApiMembership($user, ['page.update']);
    [$website, $navigation, $draft] = createNavigationItemApiWebsiteNavigationAndDraft($account);
    $item = createNavigationItemApiItem($draft, ['url' => '/x']);

    loginNavigationItemApiUser($user);

    statefulPatchNavigationItem(navigationItemUri($account, $website, $navigation, $item), [
        'label' => 'Nope',
    ])->assertForbidden();
});
