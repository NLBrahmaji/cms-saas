<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageSectionContent;
use App\Models\PageVersion;
use App\Models\PageVersionSeoSetting;
use App\Models\User;
use App\Models\Website;
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

function pageDeleteOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageDeleteCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulDeleteJsonForPages(string $uri): TestResponse
{
    $response = test()->withHeaders(pageDeleteOriginHeaders())->deleteJson($uri);

    syncPageDeleteCookies($response);

    return $response;
}

function statefulGetJsonForPageDelete(string $uri): TestResponse
{
    $response = test()->withHeaders(pageDeleteOriginHeaders())->getJson($uri);

    syncPageDeleteCookies($response);

    return $response;
}

function statefulPatchJsonForPageDelete(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageDeleteOriginHeaders())->patchJson($uri, $data);

    syncPageDeleteCookies($response);

    return $response;
}

function statefulPostJsonForPageDelete(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageDeleteOriginHeaders())->postJson($uri, $data);

    syncPageDeleteCookies($response);

    return $response;
}

function createPageDeleteUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada-page-delete-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginPageDeleteUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostJsonForPageDelete('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachPageDeleteMembership(
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

function createPageDeleteWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageDeleteUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id;
}

function pageDeleteCollectionUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages';
}

function createPageWithDraftForDelete(Website $website, User $creator, string $name = 'About Us', string $slug = 'about-us'): Page
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

function createSectionTemplateIdForDelete(): int
{
    $typeId = DB::table('section_types')->insertGetId([
        'name' => 'Hero',
        'key' => 'hero-'.uniqid(),
        'content_schema' => json_encode(['type' => 'object']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) DB::table('section_templates')->insertGetId([
        'section_type_id' => $typeId,
        'name' => 'Hero',
        'key' => 'hero-template-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function attachPageSnapshotFixtureForDelete(PageVersion $version): void
{
    PageVersionSeoSetting::query()->create([
        'page_version_id' => $version->id,
        'meta_title' => 'SEO title',
    ]);

    $section = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => createSectionTemplateIdForDelete(),
        'sort_order' => 1,
        'is_visible' => true,
        'settings' => ['layout' => 'wide'],
    ]);

    PageSectionContent::query()->create([
        'page_section_id' => $section->id,
        'content' => ['title' => 'Hello'],
    ]);
}

function simulatePublishedPageForDelete(Page $page, User $publisher): void
{
    $page->refresh();
    $version = $page->draftVersion;

    $version->update([
        'published_at' => now(),
        'published_by' => $publisher->id,
    ]);

    $page->update([
        'published_version_id' => $version->id,
    ]);
}

test('user with page delete can soft delete a page', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account');
    $website = createPageDeleteWebsite($account);
    $page = createPageWithDraftForDelete($website, $user);
    $version = $page->draftVersion;
    attachPageSnapshotFixtureForDelete($version);

    $draftPointer = $page->draft_version_id;
    $versionCount = PageVersion::query()->count();
    $seoCount = PageVersionSeoSetting::query()->count();
    $sectionCount = PageSection::query()->count();
    $contentCount = PageSectionContent::query()->count();

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($account, $website, $page))
        ->assertNoContent();

    $trashed = Page::withTrashed()->find($page->id);

    expect($trashed)->not->toBeNull()
        ->and($trashed->deleted_at)->not->toBeNull()
        ->and(Page::query()->find($page->id))->toBeNull()
        ->and($trashed->draft_version_id)->toBe($draftPointer)
        ->and($trashed->published_version_id)->toBeNull()
        ->and(PageVersion::query()->count())->toBe($versionCount)
        ->and(PageVersionSeoSetting::query()->count())->toBe($seoCount)
        ->and(PageSection::query()->count())->toBe($sectionCount)
        ->and(PageSectionContent::query()->count())->toBe($contentCount);
});

test('unauthenticated page delete is unauthorized', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account');
    $website = createPageDeleteWebsite($account);
    $page = createPageWithDraftForDelete($website, $user);

    test()->deleteJson(pageDeleteUri($account, $website, $page))
        ->assertUnauthorized();
});

test('missing page delete permission forbids deletion', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account', ['page.view']);
    $website = createPageDeleteWebsite($account);
    $page = createPageWithDraftForDelete($website, $user);

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($account, $website, $page))
        ->assertForbidden();

    expect(Page::query()->find($page->id))->not->toBeNull();
});

test('page update permission alone cannot delete', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account', ['page.view', 'page.update']);
    $website = createPageDeleteWebsite($account);
    $page = createPageWithDraftForDelete($website, $user);

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($account, $website, $page))
        ->assertForbidden();
});

test('published page can be soft deleted while versions and pointers remain', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account');
    $website = createPageDeleteWebsite($account);
    $page = createPageWithDraftForDelete($website, $user);
    $version = $page->draftVersion;

    simulatePublishedPageForDelete($page, $user);

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($account, $website, $page))
        ->assertNoContent();

    $trashed = Page::withTrashed()->findOrFail($page->id);

    expect($trashed->draft_version_id)->toBe($version->id)
        ->and($trashed->published_version_id)->toBe($version->id)
        ->and(PageVersion::query()->where('page_id', $page->id)->count())->toBe(1)
        ->and($version->refresh()->published_at)->not->toBeNull();
});

test('deleting the website homepage clears home page pointer', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account');
    $website = createPageDeleteWebsite($account);
    $home = createPageWithDraftForDelete($website, $user, 'Home', 'home');
    $other = createPageWithDraftForDelete($website, $user, 'About', 'about');

    $website->update(['home_page_id' => $home->id]);

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($account, $website, $home))
        ->assertNoContent();

    expect($website->refresh()->home_page_id)->toBeNull()
        ->and(Page::query()->find($other->id))->not->toBeNull();
});

test('soft deleting a page does not mutate navigation or redirect references', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account');
    $website = createPageDeleteWebsite($account);
    $page = createPageWithDraftForDelete($website, $user);

    $navigationId = DB::table('navigations')->insertGetId([
        'website_id' => $website->id,
        'name' => 'Main',
        'key' => 'main-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $navigationVersionId = DB::table('navigation_versions')->insertGetId([
        'navigation_id' => $navigationId,
        'version' => 1,
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $navigationItemId = DB::table('navigation_items')->insertGetId([
        'navigation_version_id' => $navigationVersionId,
        'type' => 'page',
        'page_id' => $page->id,
        'label' => 'About',
        'sort_order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $redirectId = DB::table('website_redirects')->insertGetId([
        'website_id' => $website->id,
        'source_path' => '/old-about',
        'destination_type' => 'page',
        'page_id' => $page->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($account, $website, $page))
        ->assertNoContent();

    expect(DB::table('navigation_items')->where('id', $navigationItemId)->value('page_id'))->toBe($page->id)
        ->and(DB::table('website_redirects')->where('id', $redirectId)->value('page_id'))->toBe($page->id);
});

test('deleted page is excluded from list and cannot be shown patched or deleted again', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account');
    $website = createPageDeleteWebsite($account);
    $remaining = createPageWithDraftForDelete($website, $user, 'Remaining', 'remaining');
    $deleted = createPageWithDraftForDelete($website, $user, 'Gone', 'gone');

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($account, $website, $deleted))
        ->assertNoContent();

    statefulGetJsonForPageDelete(pageDeleteCollectionUri($account, $website))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $remaining->id);

    statefulGetJsonForPageDelete(pageDeleteUri($account, $website, $deleted))->assertNotFound();
    statefulPatchJsonForPageDelete(pageDeleteUri($account, $website, $deleted), ['name' => 'Nope'])->assertNotFound();
    statefulDeleteJsonForPages(pageDeleteUri($account, $website, $deleted))->assertNotFound();
});

test('page delete returns not found for page on another website', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account');
    $websiteA = createPageDeleteWebsite($account);
    $websiteB = createPageDeleteWebsite($account);
    $pageOnB = createPageWithDraftForDelete($websiteB, $user);

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($account, $websiteA, $pageOnB))
        ->assertNotFound();
});

test('page delete returns not found for website on another account', function () {
    $user = createPageDeleteUser();
    $accountA = attachPageDeleteMembership($user, 'Account A');
    $otherUser = createPageDeleteUser();
    $accountB = attachPageDeleteMembership($otherUser, 'Account B');
    $websiteOnB = createPageDeleteWebsite($accountB);
    $page = createPageWithDraftForDelete($websiteOnB, $otherUser);

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($accountA, $websiteOnB, $page))
        ->assertNotFound();
});

test('inactive membership and account forbid page delete', function () {
    $user = createPageDeleteUser();
    $inactiveMemberAccount = attachPageDeleteMembership($user, 'Inactive Member', membershipStatus: 'inactive');
    $websiteForInactiveMember = createPageDeleteWebsite($inactiveMemberAccount);
    $pageForInactiveMember = createPageWithDraftForDelete($websiteForInactiveMember, $user);

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($inactiveMemberAccount, $websiteForInactiveMember, $pageForInactiveMember))
        ->assertForbidden();

    $inactiveAccount = attachPageDeleteMembership($user, 'Inactive Account', accountStatus: 'inactive');
    $websiteForInactiveAccount = createPageDeleteWebsite($inactiveAccount);
    $pageForInactiveAccount = createPageWithDraftForDelete($websiteForInactiveAccount, $user);

    statefulDeleteJsonForPages(pageDeleteUri($inactiveAccount, $websiteForInactiveAccount, $pageForInactiveAccount))
        ->assertForbidden();
});

test('soft deleted website cannot delete pages', function () {
    $user = createPageDeleteUser();
    $account = attachPageDeleteMembership($user, 'Ada Account');
    $website = createPageDeleteWebsite($account);
    $page = createPageWithDraftForDelete($website, $user);
    $website->delete();

    loginPageDeleteUser($user);

    statefulDeleteJsonForPages(pageDeleteUri($account, $website, $page))
        ->assertNotFound();
});
