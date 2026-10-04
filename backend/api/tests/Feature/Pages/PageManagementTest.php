<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function pageManagementOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageManagementCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetJsonForPages(string $uri): TestResponse
{
    $response = test()->withHeaders(pageManagementOriginHeaders())->getJson($uri);

    syncPageManagementCookies($response);

    return $response;
}

function statefulPostJsonForPages(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageManagementOriginHeaders())->postJson($uri, $data);

    syncPageManagementCookies($response);

    return $response;
}

function createPageManagementUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada-pages-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginPageManagementUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostJsonForPages('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachPageMembership(
    User $user,
    string $accountName,
    array $permissions = AccountPermissionSeeder::PERMISSIONS,
    string $membershipStatus = 'active',
    string $accountStatus = 'active',
): Account {
    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => $accountName,
        'status' => $accountStatus,
    ]);

    AccountMember::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'status' => $membershipStatus,
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

function createPageManagementWebsite(Account $account, string $subdomain = 'example'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => $subdomain.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageResourcePayload(Page $page, ?Website $website = null): array
{
    $page->loadMissing('draftVersion');
    $website ??= Website::query()->find($page->website_id);

    return [
        'id' => $page->id,
        'name' => $page->draftVersion->name,
        'slug' => $page->draftVersion->slug,
        'is_home' => $website !== null && (int) $website->home_page_id === (int) $page->id,
        'has_published_version' => $page->published_version_id !== null,
    ];
}

function accountPagesUri(Account $account, Website $website, ?Page $page = null): string
{
    $uri = '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages';

    if ($page !== null) {
        $uri .= '/'.$page->id;
    }

    return $uri;
}

test('unauthenticated page list create and show requests are unauthorized', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);
    $page = Page::query()->create(['website_id' => $website->id]);

    statefulGetJsonForPages(accountPagesUri($account, $website))->assertUnauthorized();
    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About'])->assertUnauthorized();
    statefulGetJsonForPages(accountPagesUri($account, $website, $page))->assertUnauthorized();
});

test('active member with page view can list and show pages', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    $createResponse = statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About Us'])
        ->assertCreated();

    $page = Page::query()->firstOrFail();

    statefulGetJsonForPages(accountPagesUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => [pageResourcePayload($page)]]);

    statefulGetJsonForPages(accountPagesUri($account, $website, $page))
        ->assertOk()
        ->assertExactJson(['data' => pageResourcePayload($page)]);

    expect($createResponse->json('data.slug'))->toBe('about-us');
});

test('missing page view permission forbids list and show', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account', ['page.create']);
    $website = createPageManagementWebsite($account);
    $page = Page::query()->create(['website_id' => $website->id]);

    loginPageManagementUser($user);

    statefulGetJsonForPages(accountPagesUri($account, $website))->assertForbidden();
    statefulGetJsonForPages(accountPagesUri($account, $website, $page))->assertForbidden();
});

test('page create permission allows page creation', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About Us'])
        ->assertCreated();
});

test('missing page create permission forbids page creation', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account', ['account.view', 'page.view']);
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About Us'])
        ->assertForbidden();
});

test('page creation persists page version one and draft pointer transactionally', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About Us'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'About Us')
        ->assertJsonPath('data.slug', 'about-us')
        ->assertJsonPath('data.is_home', false)
        ->assertJsonPath('data.has_published_version', false);

    $page = Page::query()->firstOrFail();
    $version = PageVersion::query()->firstOrFail();

    expect($page->website_id)->toBe($website->id)
        ->and($page->draft_version_id)->toBe($version->id)
        ->and($page->published_version_id)->toBeNull()
        ->and($version->page_id)->toBe($page->id)
        ->and($version->version)->toBe(1)
        ->and($version->name)->toBe('About Us')
        ->and($version->slug)->toBe('about-us')
        ->and($version->parent_page_id)->toBeNull()
        ->and($version->created_by)->toBe($user->id)
        ->and($version->published_by)->toBeNull()
        ->and($version->published_at)->toBeNull();
});

test('page creation rolls back when draft pointer cannot be assigned', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    Page::updating(function (): void {
        throw new RuntimeException('Simulated pointer failure');
    });

    try {
        statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About Us'])
            ->assertStatus(500);
    } finally {
        Page::flushEventListeners();
    }

    expect(Page::query()->count())->toBe(0)
        ->and(PageVersion::query()->count())->toBe(0);
});

test('page creation rejects client controlled fields', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), [
        'name' => 'About Us',
        'website_id' => 999,
        'page_id' => 1,
        'id' => 1,
        'slug' => 'hijacked',
        'is_home' => true,
        'parent_page_id' => 1,
        'version' => 2,
        'draft_version_id' => 1,
        'published_version_id' => 1,
        'published_at' => now()->toISOString(),
        'created_by' => 1,
        'published_by' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'website_id',
            'page_id',
            'id',
            'slug',
            'is_home',
            'parent_page_id',
            'version',
            'draft_version_id',
            'published_version_id',
            'published_at',
            'created_by',
            'published_by',
        ]);
});

test('page creation rejects unknown fields', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), [
        'name' => 'About Us',
        'unexpected' => 'value',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
});

test('page creation validates name', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    statefulPostJsonForPages(accountPagesUri($account, $website), [
        'name' => str_repeat('a', 256),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('page is created under route website regardless of client website id', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $websiteA = createPageManagementWebsite($account, 'site-a');
    $websiteB = createPageManagementWebsite($account, 'site-b');

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $websiteA), [
        'name' => 'About Us',
        'website_id' => $websiteB->id,
    ])->assertUnprocessable();

    statefulPostJsonForPages(accountPagesUri($account, $websiteA), ['name' => 'About Us'])
        ->assertCreated();

    expect(Page::query()->firstOrFail()->website_id)->toBe($websiteA->id);
});

test('duplicate page names receive deterministic unique slugs within a website', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About Us'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'about-us');

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About Us'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'about-us-2');

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About Us'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'about-us-3');
});

test('different websites may use the same initial slug', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $websiteA = createPageManagementWebsite($account, 'site-a');
    $websiteB = createPageManagementWebsite($account, 'site-b');

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $websiteA), ['name' => 'About Us'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'about-us');

    statefulPostJsonForPages(accountPagesUri($account, $websiteB), ['name' => 'About Us'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'about-us');
});

test('unusable page names still receive a safe generated slug', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => '!!!'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'page');
});

test('page list is ordered by id ascending', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'First'])->assertCreated();
    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'Second'])->assertCreated();

    $pages = Page::query()->orderBy('id')->get();

    $response = statefulGetJsonForPages(accountPagesUri($account, $website))->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe($pages->pluck('id')->all());
});

test('page list excludes soft deleted pages', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'Active'])->assertCreated();
    $active = Page::query()->firstOrFail();

    $deleted = Page::query()->create(['website_id' => $website->id]);
    $deleted->delete();

    statefulGetJsonForPages(accountPagesUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => [pageResourcePayload($active)]]);
});

test('page show returns not found for page belonging to another website', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $websiteA = createPageManagementWebsite($account, 'site-a');
    $websiteB = createPageManagementWebsite($account, 'site-b');

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $websiteB), ['name' => 'On B'])->assertCreated();
    $pageOnB = Page::query()->firstOrFail();

    statefulGetJsonForPages(accountPagesUri($account, $websiteA, $pageOnB))->assertNotFound();
});

test('website from another account cannot be used for page routes', function () {
    $user = createPageManagementUser();
    $accountA = attachPageMembership($user, 'Account A');
    $otherUser = createPageManagementUser();
    $accountB = attachPageMembership($otherUser, 'Account B');
    $websiteOnB = createPageManagementWebsite($accountB);

    loginPageManagementUser($user);

    statefulGetJsonForPages('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/pages')
        ->assertNotFound();
});

test('account scoped permissions do not authorize page operations in another account', function () {
    $user = createPageManagementUser();

    attachPageMembership($user, 'Account A');
    $accountB = attachPageMembership($user, 'Account B', ['account.view', 'page.view']);
    $websiteOnB = createPageManagementWebsite($accountB);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($accountB, $websiteOnB), ['name' => 'Blocked'])
        ->assertForbidden();
});

test('inactive membership forbids page routes', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Inactive Member', membershipStatus: 'inactive');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulGetJsonForPages(accountPagesUri($account, $website))->assertForbidden();
});

test('inactive account forbids page routes', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Inactive Account', accountStatus: 'inactive');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulGetJsonForPages(accountPagesUri($account, $website))->assertForbidden();
});

test('soft deleted website is inaccessible for page routes', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About'])->assertCreated();
    $page = Page::query()->firstOrFail();

    $website->delete();

    statefulGetJsonForPages(accountPagesUri($account, $website))->assertNotFound();
    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'Nope'])->assertNotFound();
    statefulGetJsonForPages(accountPagesUri($account, $website, $page))->assertNotFound();
});

test('soft deleted page is not listed or shown', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'Active'])->assertCreated();
    $active = Page::query()->orderBy('id')->firstOrFail();

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'To Delete'])->assertCreated();
    $deleted = Page::query()->orderByDesc('id')->firstOrFail();
    $deleted->delete();

    statefulGetJsonForPages(accountPagesUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => [pageResourcePayload($active)]]);

    statefulGetJsonForPages(accountPagesUri($account, $website, $deleted))->assertNotFound();
});

test('spatie team context is restored after successful page request', function () {
    $user = createPageManagementUser();
    $account = attachPageMembership($user, 'Ada Account');
    $website = createPageManagementWebsite($account);

    loginPageManagementUser($user);

    statefulPostJsonForPages(accountPagesUri($account, $website), ['name' => 'About'])->assertCreated();

    $registrar = app(PermissionRegistrar::class);

    expect($registrar->getPermissionsTeamId())->toBeNull();
});
