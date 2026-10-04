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

function navigationPublishUri(Account $account, Website $website, Navigation $navigation): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations/'.$navigation->id.'/publish';
}

function navigationPublishItemsUri(Account $account, Website $website, Navigation $navigation): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/navigations/'.$navigation->id.'/items';
}

function navigationPublishItemPatchUri(
    Account $account,
    Website $website,
    Navigation $navigation,
    NavigationItem $item,
): string {
    return navigationPublishItemsUri($account, $website, $navigation).'/'.$item->public_id;
}

function navigationPublishOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncNavigationPublishCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPostNavigationPublish(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(navigationPublishOriginHeaders())->postJson($uri, $data);

    syncNavigationPublishCookies($response);

    return $response;
}

function statefulPatchNavigationPublishItem(string $uri, array $data): TestResponse
{
    $response = test()->withHeaders(navigationPublishOriginHeaders())->patchJson($uri, $data);

    syncNavigationPublishCookies($response);

    return $response;
}

function statefulGetNavigationPublishItems(string $uri): TestResponse
{
    $response = test()->withHeaders(navigationPublishOriginHeaders())->getJson($uri);

    syncNavigationPublishCookies($response);

    return $response;
}

function loginNavigationPublishUser(User $user): void
{
    statefulPostNavigationPublish('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createNavigationPublishUser(): User
{
    return User::query()->create([
        'name' => 'Publish User',
        'email' => 'nav-publish-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachNavigationPublishMembership(User $user, array $permissions): Account
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

function createNavigationPublishWebsite(Account $account, string $prefix = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site',
        'subdomain' => $prefix.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
        'published_at' => null,
    ]);
}

/**
 * @return array{0: Website, 1: Navigation, 2: NavigationVersion}
 */
function createNavigationPublishNavigation(Account $account, ?Website $website = null): array
{
    $website ??= createNavigationPublishWebsite($account);
    $user = User::query()->find($account->owner_id);

    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Main Navigation',
        'key' => 'main-'.uniqid(),
    ]);

    $draft = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
        'created_by' => $user?->id,
    ]);

    $navigation->assignDraftVersion($draft);

    return [$website, $navigation, $draft];
}

function createNavigationPublishVersion(Navigation $navigation, User $user, int $version): NavigationVersion
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
function createNavigationPublishItem(NavigationVersion $version, array $overrides = []): NavigationItem
{
    return NavigationItem::query()->create(array_merge([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/item-'.uniqid(),
        'sort_order' => 0,
    ], $overrides));
}

function createNavigationPublishPageWithPublishedVersion(Website $website, User $user, bool $withAheadDraft = false): Page
{
    $page = Page::query()->create(['website_id' => $website->id]);

    $v1 = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'About',
        'slug' => 'about-'.uniqid(),
        'created_by' => $user->id,
        'published_at' => now(),
        'published_by' => $user->id,
    ]);

    $page->assignDraftVersion($v1);
    $page->assignPublishedVersion($v1);

    if ($withAheadDraft) {
        $v2 = PageVersion::query()->create([
            'page_id' => $page->id,
            'version' => 2,
            'name' => 'About draft',
            'slug' => $v1->slug,
            'created_by' => $user->id,
        ]);

        $page->assignDraftVersion($v2);
    }

    return $page->refresh();
}

function createNavigationPublishUnpublishedPage(Website $website, User $user): Page
{
    $page = Page::query()->create(['website_id' => $website->id]);

    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'Draft only',
        'slug' => 'draft-'.uniqid(),
        'created_by' => $user->id,
    ]);

    $page->assignDraftVersion($version);

    return $page;
}

test('unauthenticated navigation publish is unauthorized', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation] = array_slice(createNavigationPublishNavigation($account), 0, 2);

    test()->postJson(navigationPublishUri($account, $website, $navigation), [])
        ->assertUnauthorized();
});

test('navigation publish requires navigation publish permission', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.view']);
    [$website, $navigation] = array_slice(createNavigationPublishNavigation($account), 0, 2);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertForbidden();
});

test('navigation update permission alone cannot publish navigation', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.update']);
    [$website, $navigation] = array_slice(createNavigationPublishNavigation($account), 0, 2);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertForbidden();
});

test('page publish permission alone cannot publish navigation', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['page.publish']);
    [$website, $navigation] = array_slice(createNavigationPublishNavigation($account), 0, 2);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertForbidden();
});

test('navigation publish returns not found for wrong website nesting', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    $websiteA = createNavigationPublishWebsite($account, 'a');
    $websiteB = createNavigationPublishWebsite($account, 'b');
    [, $navigation] = createNavigationPublishNavigation($account, $websiteB);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $websiteA, $navigation))
        ->assertNotFound();
});

test('navigation publish rejects unknown request fields', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation] = array_slice(createNavigationPublishNavigation($account), 0, 2);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation), [
        'name' => 'Hijacked',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('first navigation publish sets pointers and publication metadata', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);
    createNavigationPublishItem($draft, ['url' => '/about']);

    loginNavigationPublishUser($user);

    $createdBy = $draft->created_by;

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertOk()
        ->assertJsonPath('data.has_published_version', true)
        ->assertJsonPath('data.name', 'Main Navigation');

    $navigation->refresh();
    $draft->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and($navigation->draft_version_id)->toBe($draft->id)
        ->and($navigation->published_version_id)->toBe($draft->id)
        ->and($draft->published_at)->not->toBeNull()
        ->and($draft->published_by)->toBe($user->id)
        ->and($draft->created_by)->toBe($createdBy);
});

test('empty navigation can be published', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertOk()
        ->assertJsonPath('data.has_published_version', true);

    expect($navigation->refresh()->published_version_id)->toBe($draft->id);
});

test('navigation publish accepts valid url page and nested items', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);

    $page = createNavigationPublishPageWithPublishedVersion($website, $user, true);

    $parent = createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Url,
        'url' => 'https://example.com',
        'sort_order' => 0,
        'label' => null,
    ]);
    createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'url' => null,
        'parent_id' => $parent->id,
        'sort_order' => 5,
    ]);
    createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Url,
        'url' => '/relative',
        'sort_order' => 5,
    ]);
    createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'url' => null,
        'sort_order' => 20,
    ]);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertOk();
});

test('canonical navigation republish is idempotent and preserves metadata', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))->assertOk();

    $originalPublishedAt = $draft->refresh()->published_at;
    $originalPublishedBy = $draft->published_by;
    $itemCount = NavigationItem::query()->where('navigation_version_id', $draft->id)->count();

    sleep(1);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertOk()
        ->assertJsonPath('data.has_published_version', true);

    $draft->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and($draft->published_at->eq($originalPublishedAt))->toBeTrue()
        ->and($draft->published_by)->toBe($originalPublishedBy)
        ->and(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe($itemCount);
});

test('navigation publish ahead draft promotes v2 and preserves v1', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $v1] = createNavigationPublishNavigation($account);

    createNavigationPublishItem($v1, ['url' => '/published']);
    $navigation->assignPublishedVersion($v1);
    $v1->update(['published_at' => now(), 'published_by' => $user->id]);

    $v1PublishedAt = $v1->published_at;
    $v1PublishedBy = $v1->published_by;

    $v2 = createNavigationPublishVersion($navigation, $user, 2);
    createNavigationPublishItem($v2, ['url' => '/draft']);
    $navigation->assignDraftVersion($v2);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))->assertOk();

    $navigation->refresh();
    $v1->refresh();
    $v2->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2)
        ->and($navigation->draft_version_id)->toBe($v2->id)
        ->and($navigation->published_version_id)->toBe($v2->id)
        ->and($v1->published_at->eq($v1PublishedAt))->toBeTrue()
        ->and($v1->published_by)->toBe($v1PublishedBy)
        ->and($v2->published_at)->not->toBeNull()
        ->and($v2->published_by)->toBe($user->id)
        ->and(NavigationItem::query()->where('navigation_version_id', $v1->id)->count())->toBe(1);
});

test('navigation publish rejects unpublished page target', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);
    $page = createNavigationPublishUnpublishedPage($website, $user);

    createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'url' => null,
    ]);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['navigation']);

    expect($navigation->refresh()->published_version_id)->toBeNull()
        ->and($draft->refresh()->published_at)->toBeNull();
});

test('navigation publish rejects invalid stored item targets', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);
    $otherWebsite = createNavigationPublishWebsite($account, 'other');
    $foreignPage = createNavigationPublishPageWithPublishedVersion($otherWebsite, $user);
    $localPage = createNavigationPublishPageWithPublishedVersion($website, $user);
    $localPage->delete();

    loginNavigationPublishUser($user);
    $uri = navigationPublishUri($account, $website, $navigation);

    createNavigationPublishItem($draft, ['url' => '//evil.com']);
    statefulPostNavigationPublish($uri)->assertUnprocessable();

    NavigationItem::query()->where('navigation_version_id', $draft->id)->delete();

    createNavigationPublishItem($draft, ['url' => '/ok', 'page_id' => $localPage->id]);
    statefulPostNavigationPublish($uri)->assertUnprocessable();

    NavigationItem::query()->where('navigation_version_id', $draft->id)->delete();

    createNavigationPublishItem($draft, ['type' => NavigationItemType::Page, 'page_id' => null, 'url' => null]);
    statefulPostNavigationPublish($uri)->assertUnprocessable();

    NavigationItem::query()->where('navigation_version_id', $draft->id)->delete();

    createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $localPage->id,
        'url' => '/nope',
    ]);
    statefulPostNavigationPublish($uri)->assertUnprocessable();

    NavigationItem::query()->where('navigation_version_id', $draft->id)->delete();

    createNavigationPublishItem($draft, ['type' => NavigationItemType::Url, 'url' => null, 'page_id' => null]);
    statefulPostNavigationPublish($uri)->assertUnprocessable();

    NavigationItem::query()->where('navigation_version_id', $draft->id)->delete();

    createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $foreignPage->id,
        'url' => null,
    ]);
    statefulPostNavigationPublish($uri)->assertUnprocessable();

    NavigationItem::query()->where('navigation_version_id', $draft->id)->delete();

    createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $localPage->id,
        'url' => null,
    ]);
    statefulPostNavigationPublish($uri)->assertUnprocessable();
});

test('navigation publish rejects corrupt page published pointer', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);

    $pageA = createNavigationPublishPageWithPublishedVersion($website, $user);
    $pageB = createNavigationPublishPageWithPublishedVersion($website, $user);

    $pageA->update(['published_version_id' => $pageB->published_version_id]);

    createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $pageA->id,
        'url' => null,
    ]);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['navigation']);
});

test('navigation publish on corrupt tree does not change pointers', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);

    $a = createNavigationPublishItem($draft, ['url' => '/a']);
    $b = createNavigationPublishItem($draft, ['url' => '/b', 'parent_id' => $a->id]);
    $a->update(['parent_id' => $b->id]);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
        ->assertStatus(500);

    expect($navigation->refresh()->published_version_id)->toBeNull();
});

test('navigation publish does not change website or other navigations', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigationA, $draftA] = createNavigationPublishNavigation($account);
    [, $navigationB, $draftB] = createNavigationPublishNavigation($account, $website);

    createNavigationPublishItem($draftA, ['url' => '/a']);
    createNavigationPublishItem($draftB, ['url' => '/b']);

    $websiteStatus = $website->status;
    $websitePublishedAt = $website->published_at;
    $navigationBDraftBefore = $navigationB->draft_version_id;

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigationA))->assertOk();

    $website->refresh();
    $navigationB->refresh();

    expect($website->status)->toBe($websiteStatus)
        ->and($website->published_at)->toBe($websitePublishedAt)
        ->and($navigationB->published_version_id)->toBeNull()
        ->and($navigationB->draft_version_id)->toBe($navigationBDraftBefore)
        ->and(NavigationItem::query()->where('navigation_version_id', $draftB->id)->count())->toBe(1);
});

test('navigation publish does not mutate page pointers', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);
    $page = createNavigationPublishPageWithPublishedVersion($website, $user, true);

    createNavigationPublishItem($draft, [
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'url' => null,
    ]);

    $draftPageVersion = $page->draft_version_id;
    $publishedPageVersion = $page->published_version_id;

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))->assertOk();

    $page->refresh();

    expect($page->draft_version_id)->toBe($draftPageVersion)
        ->and($page->published_version_id)->toBe($publishedPageVersion);
});

test('failed navigation publish rolls back pointer and publication metadata', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);

    loginNavigationPublishUser($user);

    NavigationVersion::updating(function (): void {
        throw new RuntimeException('Simulated publish failure');
    });

    try {
        statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))
            ->assertStatus(500);
    } finally {
        NavigationVersion::flushEventListeners();
    }

    $navigation->refresh();
    $draft->refresh();

    expect($navigation->published_version_id)->toBeNull()
        ->and($draft->published_at)->toBeNull()
        ->and($draft->published_by)->toBeNull();
});

test('get items after publish reads canonical draft snapshot', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish', 'navigation.view']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);
    $item = createNavigationPublishItem($draft, ['url' => '/visible']);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))->assertOk();

    statefulGetNavigationPublishItems(navigationPublishItemsUri($account, $website, $navigation))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $item->public_id);
});

test('publish then patch uses existing ahead draft lifecycle', function () {
    $user = createNavigationPublishUser();
    $account = attachNavigationPublishMembership($user, ['navigation.publish', 'navigation.update']);
    [$website, $navigation, $draft] = createNavigationPublishNavigation($account);
    $item = createNavigationPublishItem($draft, ['url' => '/about', 'label' => 'About']);

    loginNavigationPublishUser($user);

    statefulPostNavigationPublish(navigationPublishUri($account, $website, $navigation))->assertOk();

    statefulPatchNavigationPublishItem(
        navigationPublishItemPatchUri($account, $website, $navigation, $item),
        ['label' => 'Updated'],
    )->assertOk();

    $navigation->refresh();
    $v2 = NavigationVersion::query()->where('navigation_id', $navigation->id)->where('version', 2)->firstOrFail();

    expect($navigation->published_version_id)->toBe($draft->id)
        ->and($navigation->draft_version_id)->toBe($v2->id)
        ->and(NavigationItem::query()->where('navigation_version_id', $draft->id)->wherePublicId($item->public_id)->first()?->label)
        ->toBe('About');
});
