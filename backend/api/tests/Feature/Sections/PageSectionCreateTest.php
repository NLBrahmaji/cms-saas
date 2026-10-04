<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageSectionContent;
use App\Models\PageVersion;
use App\Models\SectionTemplate;
use App\Models\SectionType;
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

function pageSectionCreateOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageSectionCreateCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPostPageSections(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageSectionCreateOriginHeaders())->postJson($uri, $data);

    syncPageSectionCreateCookies($response);

    return $response;
}

function statefulGetPageSectionsForCreate(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionCreateOriginHeaders())->getJson($uri);

    syncPageSectionCreateCookies($response);

    return $response;
}

function loginPageSectionCreateUser(User $user, string $password = 'Str0ngPass!'): void
{
    test()->withHeaders(pageSectionCreateOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

function createPageSectionCreateUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada-section-create-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

/**
 * @param  list<string>  $permissions
 */
function attachPageSectionCreateMembership(
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

function createPageSectionCreateWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageSectionsCreateUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections';
}

function createPageSectionCreatePage(Website $website, User $creator): Page
{
    $page = Page::query()->create(['website_id' => $website->id]);

    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'About',
        'slug' => 'about',
        'created_by' => $creator->id,
    ]);

    $page->assignDraftVersion($version);

    return $page->refresh();
}

/**
 * @return array{type: SectionType, template: SectionTemplate}
 */
function createPageSectionCreateTemplateFixture(array $typeOverrides = [], array $templateOverrides = []): array
{
    $type = SectionType::query()->create(array_merge([
        'name' => 'Hero',
        'key' => 'hero-'.uniqid(),
        'content_schema' => ['type' => 'object'],
        'status' => SectionType::STATUS_ACTIVE,
    ], $typeOverrides));

    $template = SectionTemplate::query()->create(array_merge([
        'section_type_id' => $type->id,
        'name' => 'Centered Hero',
        'key' => 'hero-centered-'.uniqid(),
        'status' => SectionTemplate::STATUS_ACTIVE,
    ], $templateOverrides));

    return ['type' => $type, 'template' => $template];
}

function simulatePageSectionCreatePublished(Page $page, User $publisher): void
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

test('unauthenticated page section create is unauthorized', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $fixture = createPageSectionCreateTemplateFixture();

    test()->postJson(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
    ])->assertUnauthorized();
});

test('page update permission is required to add sections', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account', ['page.view']);
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $fixture = createPageSectionCreateTemplateFixture();

    loginPageSectionCreateUser($user);

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
    ])->assertForbidden();
});

test('add section validates template and rejects unknown fields', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);

    loginPageSectionCreateUser($user);

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_template_id']);

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => 'nope',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['section_template_id']);

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => 999999,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['section_template_id']);

    $inactiveTemplate = createPageSectionCreateTemplateFixture([], ['status' => 'inactive']);

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $inactiveTemplate['template']->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['section_template_id']);

    $inactiveType = createPageSectionCreateTemplateFixture(['status' => 'inactive']);

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $inactiveType['template']->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['section_template_id']);

    $fixture = createPageSectionCreateTemplateFixture();

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_order']);
});

test('add section on unpublished page appends to draft without cloning', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $version = $page->draftVersion;
    $fixture = createPageSectionCreateTemplateFixture([], ['key' => 'hero-centered']);

    loginPageSectionCreateUser($user);

    $firstResponse = statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 0)
        ->assertJsonPath('data.is_visible', true)
        ->assertJsonPath('data.template.key', 'hero-centered');

    expect($firstResponse->getContent())->toContain('"settings":{}')
        ->and($firstResponse->getContent())->toContain('"content":{}');

    $secondPublicId = statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 1)
        ->json('data.id');

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->refresh()->draft_version_id)->toBe($version->id)
        ->and(PageSection::query()->where('page_version_id', $version->id)->count())->toBe(2)
        ->and(PageSectionContent::query()->count())->toBe(2);

    $sections = PageSection::query()->where('page_version_id', $version->id)->orderBy('sort_order')->get();

    expect($sections[0]->sort_order)->toBe(0)
        ->and($sections[1]->sort_order)->toBe(1)
        ->and($sections[0]->public_id)->not->toBe($sections[1]->public_id)
        ->and($sections[1]->public_id)->toBe($secondPublicId);
});

test('add section after publish clones draft and leaves published snapshot unchanged', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $versionOne = $page->draftVersion;
    $fixture = createPageSectionCreateTemplateFixture();

    $sectionA = PageSection::query()->create([
        'page_version_id' => $versionOne->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 0,
        'is_visible' => true,
        'settings' => ['marker' => 'A'],
    ]);

    PageSectionContent::query()->create([
        'page_section_id' => $sectionA->id,
        'content' => ['headline' => 'A'],
    ]);

    $sectionANumericId = $sectionA->id;
    $sectionAPublicId = $sectionA->public_id;

    simulatePageSectionCreatePublished($page, $user);

    loginPageSectionCreateUser($user);

    $templateB = createPageSectionCreateTemplateFixture([], [
        'key' => 'section-b',
        'name' => 'Section B',
    ]);

    $response = statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $templateB['template']->id,
    ])->assertCreated();

    $sectionBPublicId = $response->json('data.id');
    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect($page->draft_version_id)->toBe($versionTwo->id)
        ->and($page->published_version_id)->toBe($versionOne->id)
        ->and(PageSection::query()->where('page_version_id', $versionOne->id)->count())->toBe(1)
        ->and(PageSection::query()->find($sectionANumericId)?->settings)->toBe(['marker' => 'A']);

    $v2Sections = PageSection::query()
        ->where('page_version_id', $versionTwo->id)
        ->orderBy('sort_order')
        ->get();

    expect($v2Sections)->toHaveCount(2)
        ->and($v2Sections[0]->public_id)->toBe($sectionAPublicId)
        ->and($v2Sections[0]->id)->not->toBe($sectionANumericId)
        ->and($v2Sections[1]->public_id)->toBe($sectionBPublicId)
        ->and($v2Sections[1]->sort_order)->toBe(1);
});

test('add section uses existing ahead draft without creating another version', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $versionOne = $page->draftVersion;
    $fixture = createPageSectionCreateTemplateFixture();

    simulatePageSectionCreatePublished($page, $user);

    $versionTwo = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionOne, $user);
    $page->assignDraftVersion($versionTwo);

    loginPageSectionCreateUser($user);

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
    ])->assertCreated();

    expect(PageVersion::query()->count())->toBe(2)
        ->and(PageSection::query()->where('page_version_id', $versionTwo->id)->count())->toBe(1);
});

test('soft deleted sections do not affect append sort order', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $version = $page->draftVersion;
    $fixture = createPageSectionCreateTemplateFixture();

    PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 0,
    ]);

    PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 1,
    ]);

    $deleted = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 9,
    ]);

    $deleted->delete();

    loginPageSectionCreateUser($user);

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 2);
});

test('created section appears on sections read endpoint', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $fixture = createPageSectionCreateTemplateFixture([], ['key' => 'hero-centered']);

    loginPageSectionCreateUser($user);

    $createdId = statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
    ])->assertCreated()->json('data.id');

    statefulGetPageSectionsForCreate(pageSectionsCreateUri($account, $website, $page))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $createdId);
});

test('add section returns not found for page on another website', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $websiteA = createPageSectionCreateWebsite($account);
    $websiteB = createPageSectionCreateWebsite($account);
    $pageOnB = createPageSectionCreatePage($websiteB, $user);
    $fixture = createPageSectionCreateTemplateFixture();

    loginPageSectionCreateUser($user);

    statefulPostPageSections(pageSectionsCreateUri($account, $websiteA, $pageOnB), [
        'section_template_id' => $fixture['template']->id,
    ])->assertNotFound();
});

test('add section returns not found for website on another account', function () {
    $user = createPageSectionCreateUser();
    $accountA = attachPageSectionCreateMembership($user, 'Account A');
    $otherUser = createPageSectionCreateUser();
    $accountB = attachPageSectionCreateMembership($otherUser, 'Account B');
    $websiteOnB = createPageSectionCreateWebsite($accountB);
    $page = createPageSectionCreatePage($websiteOnB, $otherUser);
    $fixture = createPageSectionCreateTemplateFixture();

    loginPageSectionCreateUser($user);

    statefulPostPageSections(pageSectionsCreateUri($accountA, $websiteOnB, $page), [
        'section_template_id' => $fixture['template']->id,
    ])->assertNotFound();
});

test('add section returns not found for soft deleted page', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $fixture = createPageSectionCreateTemplateFixture();

    loginPageSectionCreateUser($user);

    $page->delete();

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
    ])->assertNotFound();
});

test('add section rolls back clone when content row creation fails', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $versionOne = $page->draftVersion;
    $fixture = createPageSectionCreateTemplateFixture();

    $existingSection = PageSection::query()->create([
        'page_version_id' => $versionOne->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 0,
    ]);

    PageSectionContent::query()->create([
        'page_section_id' => $existingSection->id,
        'content' => ['headline' => 'Published'],
    ]);

    simulatePageSectionCreatePublished($page, $user);

    loginPageSectionCreateUser($user);

    $contentCreateAttempts = 0;

    PageSectionContent::creating(function () use (&$contentCreateAttempts): void {
        $contentCreateAttempts++;

        if ($contentCreateAttempts > 1) {
            throw new RuntimeException('Simulated content creation failure');
        }
    });

    try {
        statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
            'section_template_id' => $fixture['template']->id,
        ])->assertStatus(500);
    } finally {
        PageSectionContent::flushEventListeners();
    }

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($versionOne->id)
        ->and($page->published_version_id)->toBe($versionOne->id)
        ->and(PageSection::query()->where('page_version_id', $versionOne->id)->count())->toBe(1)
        ->and(PageSectionContent::query()->count())->toBe(1);
});

test('add section fails when draft pointer is corrupt', function () {
    $user = createPageSectionCreateUser();
    $account = attachPageSectionCreateMembership($user, 'Ada Account');
    $website = createPageSectionCreateWebsite($account);
    $page = createPageSectionCreatePage($website, $user);
    $fixture = createPageSectionCreateTemplateFixture();

    $page->update(['draft_version_id' => null]);

    loginPageSectionCreateUser($user);

    statefulPostPageSections(pageSectionsCreateUri($account, $website, $page), [
        'section_template_id' => $fixture['template']->id,
    ])->assertStatus(500);
});
