<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\Page;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteSetting;
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

function navigationApiOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncNavigationApiCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetJsonForNavigations(string $uri): TestResponse
{
    $response = test()->withHeaders(navigationApiOriginHeaders())->getJson($uri);

    syncNavigationApiCookies($response);

    return $response;
}

function statefulPostJsonForNavigations(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(navigationApiOriginHeaders())->postJson($uri, $data);

    syncNavigationApiCookies($response);

    return $response;
}

function createNavigationApiUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Nav API User',
        'email' => 'nav-api-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginNavigationApiUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostJsonForNavigations('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachNavigationApiMembership(
    User $user,
    string $accountName,
    array $permissions = AccountPermissionSeeder::PERMISSIONS,
): Account {
    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => $accountName,
        'status' => 'active',
    ]);

    AccountMember::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $role = Role::query()->create([
        'name' => 'member-'.uniqid(),
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

function createNavigationApiWebsite(Account $account, string $subdomain = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => $subdomain.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function navigationResourcePayload(Navigation $navigation): array
{
    return [
        'id' => $navigation->id,
        'name' => $navigation->name,
        'key' => $navigation->key,
        'has_published_version' => $navigation->published_version_id !== null,
    ];
}

function accountNavigationsUri(Account $account, Website $website, ?Navigation $navigation = null): string
{
    $uri = '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations';

    if ($navigation !== null) {
        $uri .= '/'.$navigation->id;
    }

    return $uri;
}

test('unauthenticated navigation list create and show requests are unauthorized', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $website = createNavigationApiWebsite($account);
    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);

    statefulGetJsonForNavigations(accountNavigationsUri($account, $website))->assertUnauthorized();
    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'Main',
        'key' => 'main',
    ])->assertUnauthorized();
    statefulGetJsonForNavigations(accountNavigationsUri($account, $website, $navigation))->assertUnauthorized();
});

test('active member with navigation view can list and show navigations', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account', [
        'navigation.view',
        'navigation.create',
    ]);
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    $createResponse = statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'Main Navigation',
        'key' => 'main',
    ])->assertCreated();

    $navigation = Navigation::query()->firstOrFail();

    statefulGetJsonForNavigations(accountNavigationsUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => [navigationResourcePayload($navigation)]]);

    statefulGetJsonForNavigations(accountNavigationsUri($account, $website, $navigation))
        ->assertOk()
        ->assertExactJson(['data' => navigationResourcePayload($navigation)]);

    expect($createResponse->json('data'))->toBe(navigationResourcePayload($navigation));
});

test('missing navigation view permission forbids list and show', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account', ['navigation.create']);
    $website = createNavigationApiWebsite($account);
    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);

    loginNavigationApiUser($user);

    statefulGetJsonForNavigations(accountNavigationsUri($account, $website))->assertForbidden();
    statefulGetJsonForNavigations(accountNavigationsUri($account, $website, $navigation))->assertForbidden();
});

test('page view permission alone does not authorize navigation read', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account', ['page.view']);
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    statefulGetJsonForNavigations(accountNavigationsUri($account, $website))->assertForbidden();
});

test('navigation create permission allows navigation creation without navigation view', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account', ['navigation.create']);
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'Footer',
        'key' => 'footer',
    ])->assertCreated();
});

test('missing navigation create permission forbids navigation creation', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account', ['navigation.view']);
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'Main',
        'key' => 'main',
    ])->assertForbidden();
});

test('page create permission alone does not authorize navigation creation', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account', ['page.create']);
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'Main',
        'key' => 'main',
    ])->assertForbidden();
});

test('navigation creation persists v1 and draft pointer transactionally', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'Main Navigation',
        'key' => 'main',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Main Navigation')
        ->assertJsonPath('data.key', 'main')
        ->assertJsonPath('data.has_published_version', false);

    $navigation = Navigation::query()->firstOrFail();
    $version = NavigationVersion::query()->firstOrFail();

    expect($navigation->website_id)->toBe($website->id)
        ->and($navigation->name)->toBe('Main Navigation')
        ->and($navigation->key)->toBe('main')
        ->and($navigation->draft_version_id)->toBe($version->id)
        ->and($navigation->published_version_id)->toBeNull()
        ->and($version->navigation_id)->toBe($navigation->id)
        ->and($version->version)->toBe(1)
        ->and($version->created_by)->toBe($user->id)
        ->and($version->published_by)->toBeNull()
        ->and($version->published_at)->toBeNull()
        ->and(NavigationItem::query()->where('navigation_version_id', $version->id)->count())->toBe(0)
        ->and(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1);
});

test('navigation creation rolls back when draft pointer cannot be assigned', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    Navigation::updating(function (): void {
        throw new RuntimeException('Simulated pointer failure');
    });

    try {
        statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
            'name' => 'Main',
            'key' => 'main',
        ])->assertStatus(500);
    } finally {
        Navigation::flushEventListeners();
    }

    expect(Navigation::query()->count())->toBe(0)
        ->and(NavigationVersion::query()->count())->toBe(0);
});

test('navigation creation rejects client controlled and unknown fields', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'Main',
        'key' => 'main',
        'website_id' => 999,
        'draft_version_id' => 1,
        'published_version_id' => 5,
        'version' => 2,
        'created_by' => 1,
        'published_by' => 1,
        'published_at' => now()->toISOString(),
        'items' => [],
        'extra' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'website_id',
            'draft_version_id',
            'published_version_id',
            'version',
            'created_by',
            'published_by',
            'published_at',
            'items',
            'extra',
        ]);
});

test('navigation creation validates name and key', function (array $payload, array $errors) {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing name' => [['key' => 'main'], ['name']],
    'name null' => [['name' => null, 'key' => 'main'], ['name']],
    'name not string' => [['name' => 123, 'key' => 'main'], ['name']],
    'name too long' => [['name' => str_repeat('a', 256), 'key' => 'main'], ['name']],
    'missing key' => [['name' => 'Main'], ['key']],
    'key null' => [['name' => 'Main', 'key' => null], ['key']],
    'key not string' => [['name' => 'Main', 'key' => 1], ['key']],
    'key too long' => [['name' => 'Main', 'key' => str_repeat('a', 101)], ['key']],
    'key invalid spaces' => [['name' => 'Main', 'key' => 'main menu'], ['key']],
    'key invalid uppercase' => [['name' => 'Main', 'key' => 'Main'], ['key']],
    'key invalid slash' => [['name' => 'Main', 'key' => '/main'], ['key']],
]);

test('duplicate navigation key within website is rejected', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'First',
        'key' => 'main',
    ])->assertCreated();

    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'Second',
        'key' => 'main',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['key']);

    expect(Navigation::query()->where('website_id', $website->id)->count())->toBe(1);
});

test('same navigation key is allowed on different websites', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $websiteA = createNavigationApiWebsite($account, 'a');
    $websiteB = createNavigationApiWebsite($account, 'b');

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $websiteA), [
        'name' => 'A Menu',
        'key' => 'main',
    ])->assertCreated();

    statefulPostJsonForNavigations(accountNavigationsUri($account, $websiteB), [
        'name' => 'B Menu',
        'key' => 'main',
    ])->assertCreated();

    expect(Navigation::query()->count())->toBe(2);
});

test('navigation list returns only route website navigations in id order', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account', ['navigation.view']);
    $websiteA = createNavigationApiWebsite($account, 'a');
    $websiteB = createNavigationApiWebsite($account, 'b');

    $first = Navigation::query()->create([
        'website_id' => $websiteA->id,
        'name' => 'First',
        'key' => 'first-'.uniqid(),
    ]);

    $second = Navigation::query()->create([
        'website_id' => $websiteA->id,
        'name' => 'Second',
        'key' => 'second-'.uniqid(),
    ]);

    Navigation::query()->create([
        'website_id' => $websiteB->id,
        'name' => 'Other Site',
        'key' => 'other-'.uniqid(),
    ]);

    loginNavigationApiUser($user);

    statefulGetJsonForNavigations(accountNavigationsUri($account, $websiteA))
        ->assertOk()
        ->assertExactJson([
            'data' => [
                navigationResourcePayload($first),
                navigationResourcePayload($second),
            ],
        ]);
});

test('navigation list returns empty collection when website has no navigations', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account', ['navigation.view']);
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    statefulGetJsonForNavigations(accountNavigationsUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('navigation resource reflects published version pointer', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account', ['navigation.view']);
    $website = createNavigationApiWebsite($account);

    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);

    $version = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
        'created_by' => $user->id,
        'published_by' => $user->id,
        'published_at' => now(),
    ]);

    $navigation->assignDraftVersion($version);
    $navigation->assignPublishedVersion($version);

    loginNavigationApiUser($user);

    statefulGetJsonForNavigations(accountNavigationsUri($account, $website, $navigation->refresh()))
        ->assertOk()
        ->assertJsonPath('data.has_published_version', true);
});

test('navigation show returns not found for navigation belonging to another website', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $websiteA = createNavigationApiWebsite($account, 'a');
    $websiteB = createNavigationApiWebsite($account, 'b');

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $websiteB), [
        'name' => 'On B',
        'key' => 'on-b',
    ])->assertCreated();

    $navigationOnB = Navigation::query()->firstOrFail();

    statefulGetJsonForNavigations(accountNavigationsUri($account, $websiteA, $navigationOnB))->assertNotFound();
});

test('website from another account cannot be used for navigation routes', function () {
    $user = createNavigationApiUser();
    $accountA = attachNavigationApiMembership($user, 'Account A');
    $otherUser = createNavigationApiUser();
    $accountB = attachNavigationApiMembership($otherUser, 'Account B');
    $websiteOnB = createNavigationApiWebsite($accountB);

    loginNavigationApiUser($user);

    statefulGetJsonForNavigations('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/navigations')
        ->assertNotFound();
});

test('account scoped permissions do not authorize navigation operations in another account', function () {
    $user = createNavigationApiUser();

    attachNavigationApiMembership($user, 'Account A');
    $accountB = attachNavigationApiMembership($user, 'Account B', ['navigation.view']);
    $websiteOnB = createNavigationApiWebsite($accountB);

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($accountB, $websiteOnB), [
        'name' => 'Blocked',
        'key' => 'blocked',
    ])->assertForbidden();
});

test('navigation creation does not mutate website pages or settings', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $website = createNavigationApiWebsite($account);

    WebsiteSetting::query()->create([
        'website_id' => $website->id,
        'site_name' => 'Stable',
    ]);

    $page = Page::query()->create(['website_id' => $website->id]);
    $website->update(['home_page_id' => $page->id, 'status' => 'draft', 'published_at' => null]);

    loginNavigationApiUser($user);

    statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
        'name' => 'Main',
        'key' => 'main',
    ])->assertCreated();

    $website->refresh();

    expect($website->home_page_id)->toBe($page->id)
        ->and($website->status)->toBe('draft')
        ->and($website->published_at)->toBeNull()
        ->and(WebsiteSetting::query()->where('website_id', $website->id)->value('site_name'))->toBe('Stable')
        ->and(Page::query()->count())->toBe(1);
});

test('valid navigation key syntax examples are accepted', function () {
    $user = createNavigationApiUser();
    $account = attachNavigationApiMembership($user, 'Account');
    $website = createNavigationApiWebsite($account);

    loginNavigationApiUser($user);

    foreach (['main', 'footer-secondary', 'utility_nav', 'menu2'] as $key) {
        statefulPostJsonForNavigations(accountNavigationsUri($account, $website), [
            'name' => 'Menu '.$key,
            'key' => $key,
        ])->assertCreated();
    }
});
