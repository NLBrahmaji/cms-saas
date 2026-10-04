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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function pagePublishOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPagePublishCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPostJsonForPagePublish(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pagePublishOriginHeaders())->postJson($uri, $data);

    syncPagePublishCookies($response);

    return $response;
}

function statefulPatchJsonForPagePublish(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pagePublishOriginHeaders())->patchJson($uri, $data);

    syncPagePublishCookies($response);

    return $response;
}

function createPagePublishUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada-page-publish-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginPagePublishUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostJsonForPagePublish('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachPagePublishMembership(
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

function createPagePublishWebsite(Account $account, string $subdomain = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => $subdomain.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
        'published_at' => null,
    ]);
}

function pagePublishCollectionUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages';
}

function pagePublishUri(Account $account, Website $website, Page $page): string
{
    return pagePublishCollectionUri($account, $website).'/'.$page->id.'/publish';
}

function pageItemUri(Account $account, Website $website, Page $page): string
{
    return pagePublishCollectionUri($account, $website).'/'.$page->id;
}

function createPageViaPublishApi(Account $account, Website $website, User $user, string $name = 'About Us'): Page
{
    loginPagePublishUser($user);

    statefulPostJsonForPagePublish(pagePublishCollectionUri($account, $website), ['name' => $name])
        ->assertCreated();

    return Page::query()->latest('id')->firstOrFail();
}

function createPageWithDraftForPublish(Website $website, User $creator, string $name = 'About Us', string $slug = 'about-us'): Page
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

function publishPage(Account $account, Website $website, Page $page): TestResponse
{
    return statefulPostJsonForPagePublish(pagePublishUri($account, $website, $page), []);
}

test('unauthenticated page publish is unauthorized', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = createPageWithDraftForPublish($website, $user);

    test()->postJson(pagePublishUri($account, $website, $page), [])
        ->assertUnauthorized();
});

test('page publish permission is required', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account', ['page.view']);
    $website = createPagePublishWebsite($account);
    $page = createPageWithDraftForPublish($website, $user);

    loginPagePublishUser($user);

    publishPage($account, $website, $page)->assertForbidden();
});

test('page update permission alone cannot publish', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account', ['page.view', 'page.update']);
    $website = createPagePublishWebsite($account);
    $page = createPageWithDraftForPublish($website, $user);

    loginPagePublishUser($user);

    publishPage($account, $website, $page)->assertForbidden();
});

test('page delete permission alone cannot publish', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account', ['page.view', 'page.delete']);
    $website = createPagePublishWebsite($account);
    $page = createPageWithDraftForPublish($website, $user);

    loginPagePublishUser($user);

    publishPage($account, $website, $page)->assertForbidden();
});

test('page publish rejects unknown request fields', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = createPageViaPublishApi($account, $website, $user);

    statefulPostJsonForPagePublish(pagePublishUri($account, $website, $page), [
        'slug' => 'hijacked',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('first page publish sets pointers and publication metadata', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = createPageViaPublishApi($account, $website, $user);
    $version = PageVersion::query()->firstOrFail();

    $response = publishPage($account, $website, $page)->assertOk();

    $page->refresh();
    $version->refresh();

    $response->assertJsonPath('data.has_published_version', true)
        ->assertJsonPath('data.slug', 'about-us');

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($version->id)
        ->and($page->published_version_id)->toBe($version->id)
        ->and($version->published_at)->not->toBeNull()
        ->and($version->published_by)->toBe($user->id);
});

test('repeated publish is idempotent and preserves publication metadata', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = createPageViaPublishApi($account, $website, $user);

    publishPage($account, $website, $page)->assertOk();

    $version = PageVersion::query()->firstOrFail();
    $originalPublishedAt = $version->published_at;
    $originalPublishedBy = $version->published_by;

    sleep(1);

    publishPage($account, $website, $page)
        ->assertOk()
        ->assertJsonPath('data.has_published_version', true);

    $page->refresh();
    $version->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($version->id)
        ->and($page->published_version_id)->toBe($version->id)
        ->and($version->published_at->eq($originalPublishedAt))->toBeTrue()
        ->and($version->published_by)->toBe($originalPublishedBy);
});

test('republishing a newer draft preserves the previous published version', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = createPageViaPublishApi($account, $website, $user);
    $versionOne = PageVersion::query()->firstOrFail();

    publishPage($account, $website, $page)->assertOk();

    $versionOnePublishedAt = $versionOne->refresh()->published_at;
    $versionOnePublishedBy = $versionOne->published_by;

    statefulPatchJsonForPagePublish(pageItemUri($account, $website, $page), ['name' => 'Draft edit'])
        ->assertOk();

    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    publishPage($account, $website, $page)->assertOk();

    $page->refresh();
    $versionOne->refresh();
    $versionTwo->refresh();

    expect(PageVersion::query()->count())->toBe(2)
        ->and($page->draft_version_id)->toBe($versionTwo->id)
        ->and($page->published_version_id)->toBe($versionTwo->id)
        ->and($versionOne->name)->toBe('About Us')
        ->and($versionOne->published_at->eq($versionOnePublishedAt))->toBeTrue()
        ->and($versionOne->published_by)->toBe($versionOnePublishedBy)
        ->and($versionTwo->published_at)->not->toBeNull()
        ->and($versionTwo->published_by)->toBe($user->id);
});

test('published slug conflict with another page returns validation error', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);

    $publishedPage = createPageWithDraftForPublish($website, $user, 'Published', 'about-us');
    $draftPage = createPageWithDraftForPublish($website, $user, 'Draft', 'about-us');

    loginPagePublishUser($user);

    publishPage($account, $website, $publishedPage)->assertOk();

    publishPage($account, $website, $draftPage)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);

    expect($draftPage->refresh()->published_version_id)->toBeNull();
});

test('draft only slug on another page does not block publishing', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);

    createPageWithDraftForPublish($website, $user, 'Draft only', 'about-us');
    $toPublish = createPageWithDraftForPublish($website, $user, 'Publisher', 'about-us');

    loginPagePublishUser($user);

    publishPage($account, $website, $toPublish)->assertOk();
});

test('historical published version slug does not block publishing', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);

    $page = createPageWithDraftForPublish($website, $user, 'Republish', 'about-us');

    loginPagePublishUser($user);

    publishPage($account, $website, $page)->assertOk();

    statefulPatchJsonForPagePublish(pageItemUri($account, $website, $page), ['slug' => 'new-slug'])
        ->assertOk();

    publishPage($account, $website, $page)->assertOk();

    $other = createPageWithDraftForPublish($website, $user, 'Other', 'about-us');

    publishPage($account, $website, $other)->assertOk();
});

test('soft deleted page published slug does not block publishing', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);

    $deletedPage = createPageWithDraftForPublish($website, $user, 'Deleted', 'about-us');
    $activePage = createPageWithDraftForPublish($website, $user, 'Active', 'about-us');

    loginPagePublishUser($user);

    publishPage($account, $website, $deletedPage)->assertOk();
    $deletedPage->delete();

    publishPage($account, $website, $activePage)->assertOk();
});

test('same slug in another website does not block publishing', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $websiteA = createPagePublishWebsite($account, 'a');
    $websiteB = createPagePublishWebsite($account, 'b');

    $pageA = createPageWithDraftForPublish($websiteA, $user, 'A', 'about-us');
    $pageB = createPageWithDraftForPublish($websiteB, $user, 'B', 'about-us');

    loginPagePublishUser($user);

    publishPage($account, $websiteA, $pageA)->assertOk();
    publishPage($account, $websiteB, $pageB)->assertOk();
});

test('page publishing does not change website home page pointer', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $home = createPageWithDraftForPublish($website, $user, 'Home', 'home');
    $other = createPageWithDraftForPublish($website, $user, 'About', 'about');

    $website->update(['home_page_id' => $home->id]);

    loginPagePublishUser($user);

    publishPage($account, $website, $other)->assertOk();

    expect($website->refresh()->home_page_id)->toBe($home->id);
});

test('publishing accepts a valid same website parent page', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $parent = createPageWithDraftForPublish($website, $user, 'Parent', 'parent');
    $child = createPageWithDraftForPublish($website, $user, 'Child', 'child');

    $child->draftVersion->update(['parent_page_id' => $parent->id]);

    loginPagePublishUser($user);

    publishPage($account, $website, $child)->assertOk();
});

test('publishing rejects invalid parent relationships', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $websiteA = createPagePublishWebsite($account, 'a');
    $websiteB = createPagePublishWebsite($account, 'b');

    $foreignParent = createPageWithDraftForPublish($websiteB, $user, 'Foreign', 'foreign');
    $child = createPageWithDraftForPublish($websiteA, $user, 'Child', 'child');
    $child->draftVersion->update(['parent_page_id' => $foreignParent->id]);

    loginPagePublishUser($user);

    publishPage($account, $websiteA, $child)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_page_id']);

    $selfParent = createPageWithDraftForPublish($websiteA, $user, 'Self', 'self');
    $selfParent->draftVersion->update(['parent_page_id' => $selfParent->id]);

    publishPage($account, $websiteA, $selfParent)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_page_id']);

    $deletedParent = createPageWithDraftForPublish($websiteA, $user, 'Gone', 'gone');
    $orphan = createPageWithDraftForPublish($websiteA, $user, 'Orphan', 'orphan');
    $deletedParent->delete();
    $orphan->draftVersion->update(['parent_page_id' => $deletedParent->id]);

    publishPage($account, $websiteA, $orphan)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_page_id']);
});

test('assign published version rejects another pages version', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $pageA = createPageWithDraftForPublish($website, $user, 'A', 'a');
    $pageB = createPageWithDraftForPublish($website, $user, 'B', 'b');

    $foreignVersion = $pageB->draftVersion;

    expect(fn () => $pageA->assignPublishedVersion($foreignVersion))
        ->toThrow(InvalidArgumentException::class);
});

test('missing draft cannot be published', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = Page::query()->create(['website_id' => $website->id]);

    loginPagePublishUser($user);

    publishPage($account, $website, $page)->assertStatus(500);
});

test('page publishing does not change website status or published at', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = createPageViaPublishApi($account, $website, $user);

    $status = $website->status;
    $publishedAt = $website->published_at;

    publishPage($account, $website, $page)->assertOk();

    $website->refresh();

    expect($website->status)->toBe($status)
        ->and($website->published_at)->toBe($publishedAt);
});

test('publish then patch keeps published version immutable', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = createPageViaPublishApi($account, $website, $user);
    $versionOne = PageVersion::query()->firstOrFail();

    publishPage($account, $website, $page)->assertOk();

    statefulPatchJsonForPagePublish(pageItemUri($account, $website, $page), ['name' => 'Edited draft'])
        ->assertOk();

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect($page->published_version_id)->toBe($versionOne->id)
        ->and($page->draft_version_id)->toBe($versionTwo->id)
        ->and($versionOne->refresh()->name)->toBe('About Us')
        ->and($versionTwo->name)->toBe('Edited draft');
});

test('failed publish rolls back pointer and publication metadata changes', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = createPageViaPublishApi($account, $website, $user);

    PageVersion::updating(function (): void {
        throw new RuntimeException('Simulated publish failure');
    });

    try {
        publishPage($account, $website, $page)->assertStatus(500);
    } finally {
        PageVersion::flushEventListeners();
    }

    $page->refresh();
    $version = PageVersion::query()->firstOrFail();

    expect($page->published_version_id)->toBeNull()
        ->and($version->published_at)->toBeNull()
        ->and($version->published_by)->toBeNull();
});

test('page publish returns not found for page on another website', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $websiteA = createPagePublishWebsite($account, 'a');
    $websiteB = createPagePublishWebsite($account, 'b');
    $pageOnB = createPageWithDraftForPublish($websiteB, $user);

    loginPagePublishUser($user);

    statefulPostJsonForPagePublish(pagePublishUri($account, $websiteA, $pageOnB), [])
        ->assertNotFound();
});

test('page publish returns not found for website on another account', function () {
    $user = createPagePublishUser();
    $accountA = attachPagePublishMembership($user, 'Account A');
    $otherUser = createPagePublishUser();
    $accountB = attachPagePublishMembership($otherUser, 'Account B');
    $websiteOnB = createPagePublishWebsite($accountB);
    $page = createPageWithDraftForPublish($websiteOnB, $otherUser);

    loginPagePublishUser($user);

    statefulPostJsonForPagePublish(pagePublishUri($accountA, $websiteOnB, $page), [])
        ->assertNotFound();
});

test('inactive membership and account forbid page publish', function () {
    $user = createPagePublishUser();
    $inactiveMemberAccount = attachPagePublishMembership($user, 'Inactive Member', membershipStatus: 'inactive');
    $websiteForInactiveMember = createPagePublishWebsite($inactiveMemberAccount);
    $pageForInactiveMember = createPageWithDraftForPublish($websiteForInactiveMember, $user);

    loginPagePublishUser($user);

    publishPage($inactiveMemberAccount, $websiteForInactiveMember, $pageForInactiveMember)
        ->assertForbidden();

    $inactiveAccount = attachPagePublishMembership($user, 'Inactive Account', accountStatus: 'inactive');
    $websiteForInactiveAccount = createPagePublishWebsite($inactiveAccount);
    $pageForInactiveAccount = createPageWithDraftForPublish($websiteForInactiveAccount, $user);

    publishPage($inactiveAccount, $websiteForInactiveAccount, $pageForInactiveAccount)
        ->assertForbidden();
});

test('soft deleted website and page cannot be published', function () {
    $user = createPagePublishUser();
    $account = attachPagePublishMembership($user, 'Ada Account');
    $website = createPagePublishWebsite($account);
    $page = createPageWithDraftForPublish($website, $user);

    loginPagePublishUser($user);

    $page->delete();

    publishPage($account, $website, $page)->assertNotFound();

    $page = createPageWithDraftForPublish($website, $user);
    $website->delete();

    publishPage($account, $website, $page)->assertNotFound();
});
