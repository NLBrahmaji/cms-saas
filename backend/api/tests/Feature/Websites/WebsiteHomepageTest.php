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
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function websiteHomepageOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncWebsiteHomepageCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPutJsonForWebsiteHomepage(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(websiteHomepageOriginHeaders())->putJson($uri, $data);

    syncWebsiteHomepageCookies($response);

    return $response;
}

function statefulPostJsonForWebsiteHomepage(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(websiteHomepageOriginHeaders())->postJson($uri, $data);

    syncWebsiteHomepageCookies($response);

    return $response;
}

function statefulGetJsonForWebsiteHomepage(string $uri): TestResponse
{
    $response = test()->withHeaders(websiteHomepageOriginHeaders())->getJson($uri);

    syncWebsiteHomepageCookies($response);

    return $response;
}

function createWebsiteHomepageUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada-homepage-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginWebsiteHomepageUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostJsonForWebsiteHomepage('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachWebsiteHomepageMembership(
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

function createWebsiteForHomepage(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
        'published_at' => null,
    ]);
}

function websiteHomepageUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/homepage';
}

function websitePagesUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages';
}

function createPageWithDraftForHomepage(Website $website, User $creator, string $name, string $slug): Page
{
    $page = Page::query()->create([
        'website_id' => $website->id,
    ]);

    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => $name,
        'slug' => $slug,
        'created_by' => $creator->id,
    ]);

    $page->assignDraftVersion($version);

    return $page->refresh();
}

test('homepage schema migrations apply cleanly', function () {
    expect(Schema::hasColumn('websites', 'home_page_id'))->toBeTrue()
        ->and(Schema::hasColumn('page_versions', 'is_home'))->toBeFalse();
});

test('website homepage can be selected with website update permission', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $website = createWebsiteForHomepage($account);
    $page = createPageWithDraftForHomepage($website, $user, 'Home', 'home');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $page->id])
        ->assertOk()
        ->assertJsonPath('data.home_page_id', $page->id);

    expect($website->refresh()->home_page_id)->toBe($page->id);

    statefulGetJsonForWebsiteHomepage(websitePagesUri($account, $website))
        ->assertOk()
        ->assertJsonPath('data.0.is_home', true);
});

test('homepage can switch from one page to another without version changes', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $website = createWebsiteForHomepage($account);
    $pageA = createPageWithDraftForHomepage($website, $user, 'A', 'a');
    $pageB = createPageWithDraftForHomepage($website, $user, 'B', 'b');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $pageA->id])
        ->assertOk();

    $versionCountBefore = PageVersion::query()->count();

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $pageB->id])
        ->assertOk()
        ->assertJsonPath('data.home_page_id', $pageB->id);

    $response = statefulGetJsonForWebsiteHomepage(websitePagesUri($account, $website))->assertOk();

    expect(PageVersion::query()->count())->toBe($versionCountBefore)
        ->and(collect($response->json('data'))->keyBy('id')->get($pageA->id)['is_home'])->toBeFalse()
        ->and(collect($response->json('data'))->keyBy('id')->get($pageB->id)['is_home'])->toBeTrue();
});

test('homepage can be cleared and clearing is idempotent', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $website = createWebsiteForHomepage($account);
    $page = createPageWithDraftForHomepage($website, $user, 'Home', 'home');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $page->id])
        ->assertOk();

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => null])
        ->assertOk()
        ->assertJsonPath('data.home_page_id', null);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => null])
        ->assertOk();

    $response = statefulGetJsonForWebsiteHomepage(websitePagesUri($account, $website))->assertOk();

    expect(collect($response->json('data'))->every(fn (array $item) => $item['is_home'] === false))->toBeTrue();
});

test('reselecting current homepage is idempotent', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $website = createWebsiteForHomepage($account);
    $page = createPageWithDraftForHomepage($website, $user, 'Home', 'home');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $page->id])
        ->assertOk();

    $versionCount = PageVersion::query()->count();

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $page->id])
        ->assertOk();

    expect(PageVersion::query()->count())->toBe($versionCount);
});

test('unpublished page may be selected as homepage', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $website = createWebsiteForHomepage($account);
    $page = createPageWithDraftForHomepage($website, $user, 'Home', 'home');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $page->id])
        ->assertOk();

    expect($page->refresh()->published_version_id)->toBeNull()
        ->and($website->refresh()->status)->toBe('draft')
        ->and($website->published_at)->toBeNull();
});

test('website update permission is required to set homepage', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account', ['website.view', 'page.publish']);
    $website = createWebsiteForHomepage($account);
    $page = createPageWithDraftForHomepage($website, $user, 'Home', 'home');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $page->id])
        ->assertForbidden();
});

test('homepage request rejects missing and unknown fields', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $website = createWebsiteForHomepage($account);

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page_id']);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), [
        'page_id' => null,
        'slug' => 'nope',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('cross website page selection is rejected', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $websiteA = createWebsiteForHomepage($account);
    $websiteB = createWebsiteForHomepage($account);
    $pageOnB = createPageWithDraftForHomepage($websiteB, $user, 'On B', 'on-b');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $websiteA), ['page_id' => $pageOnB->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page_id']);
});

test('soft deleted page cannot be selected as homepage', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $website = createWebsiteForHomepage($account);
    $page = createPageWithDraftForHomepage($website, $user, 'Home', 'home');
    $page->delete();

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $page->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['page_id']);
});

test('assign home page rejects another websites page', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $websiteA = createWebsiteForHomepage($account);
    $websiteB = createWebsiteForHomepage($account);
    $pageOnB = createPageWithDraftForHomepage($websiteB, $user, 'On B', 'on-b');

    expect(fn () => $websiteA->assignHomePage($pageOnB))
        ->toThrow(InvalidArgumentException::class);
});

test('homepage selection respects tenant isolation', function () {
    $user = createWebsiteHomepageUser();
    $accountA = attachWebsiteHomepageMembership($user, 'Account A');
    $otherUser = createWebsiteHomepageUser();
    $accountB = attachWebsiteHomepageMembership($otherUser, 'Account B');
    $websiteOnB = createWebsiteForHomepage($accountB);
    $page = createPageWithDraftForHomepage($websiteOnB, $otherUser, 'Home', 'home');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/homepage', [
        'page_id' => $page->id,
    ])->assertNotFound();
});

test('inactive membership and account forbid homepage selection', function () {
    $user = createWebsiteHomepageUser();
    $inactiveMemberAccount = attachWebsiteHomepageMembership($user, 'Inactive', membershipStatus: 'inactive');
    $website = createWebsiteForHomepage($inactiveMemberAccount);
    $page = createPageWithDraftForHomepage($website, $user, 'Home', 'home');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($inactiveMemberAccount, $website), ['page_id' => $page->id])
        ->assertForbidden();

    $inactiveAccount = attachWebsiteHomepageMembership($user, 'Inactive Account', accountStatus: 'inactive');
    $websiteOnInactiveAccount = createWebsiteForHomepage($inactiveAccount);
    $pageOnInactiveAccount = createPageWithDraftForHomepage($websiteOnInactiveAccount, $user, 'Home', 'home');

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($inactiveAccount, $websiteOnInactiveAccount), [
        'page_id' => $pageOnInactiveAccount->id,
    ])->assertForbidden();
});

test('soft deleted website cannot update homepage', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $website = createWebsiteForHomepage($account);
    $page = createPageWithDraftForHomepage($website, $user, 'Home', 'home');
    $website->delete();

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $page->id])
        ->assertNotFound();
});

test('selecting unpublished homepage then publishing preserves homepage pointer', function () {
    $user = createWebsiteHomepageUser();
    $account = attachWebsiteHomepageMembership($user, 'Ada Account');
    $website = createWebsiteForHomepage($account);
    $page = createPageWithDraftForHomepage($website, $user, 'Home', 'home');

    loginWebsiteHomepageUser($user);

    statefulPutJsonForWebsiteHomepage(websiteHomepageUri($account, $website), ['page_id' => $page->id])
        ->assertOk();

    statefulPostJsonForWebsiteHomepage(
        websitePagesUri($account, $website).'/'.$page->id.'/publish',
        [],
    )->assertOk();

    expect($website->refresh()->home_page_id)->toBe($page->id);
});
