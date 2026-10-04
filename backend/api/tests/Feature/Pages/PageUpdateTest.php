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
use App\Support\Page\PageVersionSnapshotCloner;
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

function pageUpdateOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageUpdateCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPatchJsonForPageUpdate(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageUpdateOriginHeaders())->patchJson($uri, $data);

    syncPageUpdateCookies($response);

    return $response;
}

function statefulPostJsonForPageUpdate(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageUpdateOriginHeaders())->postJson($uri, $data);

    syncPageUpdateCookies($response);

    return $response;
}

function createPageUpdateUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada-page-update-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginPageUpdateUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostJsonForPageUpdate('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachPageUpdateMembership(
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

function createPageUpdateWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageUpdateUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id;
}

function pageUpdatePagesCollectionUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages';
}

function createPageViaApi(Account $account, Website $website, User $user, string $name = 'About Us'): Page
{
    loginPageUpdateUser($user);

    statefulPostJsonForPageUpdate(pageUpdatePagesCollectionUri($account, $website), ['name' => $name])
        ->assertCreated();

    return Page::query()->latest('id')->firstOrFail();
}

function createPageWithDraft(Website $website, User $creator, string $name = 'About Us', string $slug = 'about-us'): Page
{
    $page = Page::query()->create([
        'website_id' => $website->id,
    ]);

    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => $name,
        'slug' => $slug,
        'is_home' => false,
        'created_by' => $creator->id,
    ]);

    $page->assignDraftVersion($version);

    return $page->refresh();
}

function simulatePagePublished(Page $page, User $publisher): void
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

function createSectionTemplateId(): int
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

function attachPublishedSnapshotFixture(PageVersion $version): array
{
    PageVersionSeoSetting::query()->create([
        'page_version_id' => $version->id,
        'meta_title' => 'Published title',
        'meta_description' => 'Published description',
        'robots_index' => true,
        'robots_follow' => true,
    ]);

    $templateId = createSectionTemplateId();

    $visibleSection = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $templateId,
        'sort_order' => 1,
        'is_visible' => true,
        'settings' => ['theme' => 'dark'],
    ]);

    PageSectionContent::query()->create([
        'page_section_id' => $visibleSection->id,
        'content' => ['headline' => 'Hello'],
    ]);

    $deletedSection = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $templateId,
        'sort_order' => 2,
        'is_visible' => false,
        'settings' => ['theme' => 'removed'],
    ]);

    PageSectionContent::query()->create([
        'page_section_id' => $deletedSection->id,
        'content' => ['headline' => 'Removed'],
    ]);

    $deletedSection->delete();

    return [
        'visible_section_id' => $visibleSection->id,
        'deleted_section_id' => $deletedSection->id,
    ];
}

test('unauthenticated page patch is unauthorized', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageWithDraft($website, $user);

    test()->patchJson(pageUpdateUri($account, $website, $page), ['name' => 'Nope'])
        ->assertUnauthorized();
});

test('page update permission is required to patch', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account', ['page.view']);
    $website = createPageUpdateWebsite($account);
    $page = createPageWithDraft($website, $user);

    loginPageUpdateUser($user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'Nope'])
        ->assertForbidden();
});

test('page patch updates name without changing slug', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'Our Company'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Our Company')
        ->assertJsonPath('data.slug', 'about-us');

    expect(PageVersion::query()->count())->toBe(1)
        ->and(PageVersion::query()->first()->name)->toBe('Our Company')
        ->and(PageVersion::query()->first()->slug)->toBe('about-us');
});

test('page patch can update slug only', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['slug' => 'company'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'company')
        ->assertJsonPath('data.name', 'About Us');
});

test('page patch normalizes slug input', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['slug' => 'About Our Company'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'about-our-company');
});

test('empty page patch succeeds without mutation or new version', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);

    $version = PageVersion::query()->firstOrFail();
    $updatedAt = $version->updated_at;

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), [])
        ->assertOk()
        ->assertJsonPath('data.name', 'About Us')
        ->assertJsonPath('data.slug', 'about-us');

    expect(PageVersion::query()->count())->toBe(1)
        ->and(PageVersion::query()->first()->updated_at->eq($updatedAt))->toBeTrue();
});

test('page patch rejects unknown and server controlled fields', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), [
        'name' => 'Valid',
        'draft_version_id' => 99,
        'unexpected' => 'nope',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['draft_version_id', 'unexpected']);
});

test('page patch validates name and slug', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['slug' => '!!!'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
});

test('unpublished page patch mutates version one without creating version two', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);
    $versionOne = PageVersion::query()->firstOrFail();

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'First edit'])
        ->assertOk();

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'Second edit'])
        ->assertOk();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->refresh()->draft_version_id)->toBe($versionOne->id)
        ->and($page->published_version_id)->toBeNull()
        ->and(PageVersion::query()->first()->name)->toBe('Second edit');
});

test('first patch after simulated publish clones published snapshot into version two', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);
    $versionOne = PageVersion::query()->firstOrFail();

    simulatePagePublished($page, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'Draft edit'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Draft edit');

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect(PageVersion::query()->count())->toBe(2)
        ->and($page->published_version_id)->toBe($versionOne->id)
        ->and($page->draft_version_id)->toBe($versionTwo->id)
        ->and($versionOne->refresh()->name)->toBe('About Us')
        ->and($versionTwo->name)->toBe('Draft edit')
        ->and($versionTwo->published_at)->toBeNull()
        ->and($versionTwo->published_by)->toBeNull()
        ->and($versionTwo->created_by)->toBe($user->id);
});

test('second patch after publish mutates version two without creating version three', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);

    simulatePagePublished($page, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'Draft edit'])
        ->assertOk();

    $versionTwoId = Page::query()->firstOrFail()->draft_version_id;

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['slug' => 'draft-slug'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'draft-slug');

    expect(PageVersion::query()->count())->toBe(2)
        ->and(Page::query()->firstOrFail()->draft_version_id)->toBe($versionTwoId);
});

test('empty patch after simulated publish does not clone a new draft', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);
    $versionOne = PageVersion::query()->firstOrFail();

    simulatePagePublished($page, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), [])
        ->assertOk();

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($versionOne->id)
        ->and($page->published_version_id)->toBe($versionOne->id);
});

test('published snapshot clone copies seo sections and content independently', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);
    $versionOne = PageVersion::query()->firstOrFail();

    $fixture = attachPublishedSnapshotFixture($versionOne);
    simulatePagePublished($page, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'Edited draft'])
        ->assertOk();

    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect(PageVersionSeoSetting::query()->where('page_version_id', $versionOne->id)->count())->toBe(1)
        ->and(PageVersionSeoSetting::query()->where('page_version_id', $versionTwo->id)->count())->toBe(1)
        ->and(PageSection::query()->where('page_version_id', $versionOne->id)->count())->toBe(1)
        ->and(PageSection::withTrashed()->where('page_version_id', $versionOne->id)->count())->toBe(2)
        ->and(PageSection::query()->where('page_version_id', $versionTwo->id)->count())->toBe(1);

    $clonedSection = PageSection::query()->where('page_version_id', $versionTwo->id)->firstOrFail();
    $clonedContent = PageSectionContent::query()->where('page_section_id', $clonedSection->id)->firstOrFail();

    expect($clonedSection->id)->not->toBe($fixture['visible_section_id'])
        ->and($clonedSection->sort_order)->toBe(1)
        ->and($clonedSection->settings)->toBe(['theme' => 'dark'])
        ->and($clonedContent->content)->toBe(['headline' => 'Hello']);

    $clonedSection->update(['settings' => ['theme' => 'mutated']]);

    expect(PageSection::query()->find($fixture['visible_section_id'])?->settings)->toBe(['theme' => 'dark']);
});

test('soft deleted sections are not cloned into the new draft', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);
    $versionOne = PageVersion::query()->firstOrFail();

    attachPublishedSnapshotFixture($versionOne);
    simulatePagePublished($page, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'Edited'])
        ->assertOk();

    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect(PageSection::query()->where('page_version_id', $versionTwo->id)->count())->toBe(1)
        ->and(PageSection::withTrashed()->where('page_version_id', $versionTwo->id)->count())->toBe(1);
});

test('snapshot cloner rejects source version from another page', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $pageA = createPageViaApi($account, $website, $user, 'Page A');
    $pageB = createPageViaApi($account, $website, $user, 'Page B');

    $foreignVersion = PageVersion::query()->where('page_id', $pageB->id)->firstOrFail();

    $cloner = app(PageVersionSnapshotCloner::class);

    expect(fn () => $cloner->cloneToNewDraftVersion($pageA, $foreignVersion, $user))
        ->toThrow(InvalidArgumentException::class);
});

test('page patch rolls back clone when section copy fails', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);
    $versionOne = PageVersion::query()->firstOrFail();

    attachPublishedSnapshotFixture($versionOne);
    simulatePagePublished($page, $user);

    PageSection::creating(function (): void {
        throw new RuntimeException('Simulated section clone failure');
    });

    try {
        statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'Edited'])
            ->assertStatus(500);
    } finally {
        PageSection::flushEventListeners();
    }

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($versionOne->id)
        ->and($page->published_version_id)->toBe($versionOne->id)
        ->and(PageSection::query()->where('page_version_id', $versionOne->id)->count())->toBe(1);
});

test('page patch returns not found for page on another website', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $websiteA = createPageUpdateWebsite($account);
    $websiteB = createPageUpdateWebsite($account);
    $pageOnB = createPageViaApi($account, $websiteB, $user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $websiteA, $pageOnB), ['name' => 'Nope'])
        ->assertNotFound();
});

test('page patch returns not found for website on another account', function () {
    $user = createPageUpdateUser();
    $accountA = attachPageUpdateMembership($user, 'Account A');
    $otherUser = createPageUpdateUser();
    $accountB = attachPageUpdateMembership($otherUser, 'Account B');
    $websiteOnB = createPageUpdateWebsite($accountB);
    $page = createPageViaApi($accountB, $websiteOnB, $otherUser);

    loginPageUpdateUser($user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($accountA, $websiteOnB, $page), ['name' => 'Nope'])
        ->assertNotFound();
});

test('inactive membership and account forbid page patch', function () {
    $user = createPageUpdateUser();
    $inactiveMemberAccount = attachPageUpdateMembership($user, 'Inactive Member', membershipStatus: 'inactive');
    $websiteForInactiveMember = createPageUpdateWebsite($inactiveMemberAccount);
    $pageForInactiveMember = Page::query()->create(['website_id' => $websiteForInactiveMember->id]);

    loginPageUpdateUser($user);

    statefulPatchJsonForPageUpdate(pageUpdateUri($inactiveMemberAccount, $websiteForInactiveMember, $pageForInactiveMember), ['name' => 'Nope'])
        ->assertForbidden();

    $inactiveAccount = attachPageUpdateMembership($user, 'Inactive Account', accountStatus: 'inactive');
    $websiteForInactiveAccount = createPageUpdateWebsite($inactiveAccount);
    $pageForInactiveAccount = Page::query()->create(['website_id' => $websiteForInactiveAccount->id]);

    statefulPatchJsonForPageUpdate(pageUpdateUri($inactiveAccount, $websiteForInactiveAccount, $pageForInactiveAccount), ['name' => 'Nope'])
        ->assertForbidden();
});

test('soft deleted page cannot be patched', function () {
    $user = createPageUpdateUser();
    $account = attachPageUpdateMembership($user, 'Ada Account');
    $website = createPageUpdateWebsite($account);
    $page = createPageViaApi($account, $website, $user);
    $page->delete();

    statefulPatchJsonForPageUpdate(pageUpdateUri($account, $website, $page), ['name' => 'Nope'])
        ->assertNotFound();
});
