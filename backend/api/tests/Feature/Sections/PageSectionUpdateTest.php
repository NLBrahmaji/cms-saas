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

function pageSectionUpdateOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageSectionUpdateCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPatchPageSection(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageSectionUpdateOriginHeaders())->patchJson($uri, $data);

    syncPageSectionUpdateCookies($response);

    return $response;
}

function statefulPatchPageSectionRaw(string $uri, string $json): TestResponse
{
    $response = test()->call(
        'PATCH',
        $uri,
        [],
        [],
        [],
        [
            'HTTP_ORIGIN' => 'http://localhost:3001',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ],
        $json,
    );

    syncPageSectionUpdateCookies($response);

    return $response;
}

function statefulGetPageSectionUpdateList(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionUpdateOriginHeaders())->getJson($uri);

    syncPageSectionUpdateCookies($response);

    return $response;
}

function statefulPostPageSectionUpdatePublish(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionUpdateOriginHeaders())->postJson($uri);

    syncPageSectionUpdateCookies($response);

    return $response;
}

function loginPageSectionUpdateUser(User $user): void
{
    test()->withHeaders(pageSectionUpdateOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createPageSectionUpdateUser(): User
{
    return User::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada-section-update-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachPageSectionUpdateMembership(User $user, array $permissions = AccountPermissionSeeder::PERMISSIONS): Account
{
    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => 'Ada Account',
        'status' => 'active',
    ]);

    AccountMember::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'status' => 'active',
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

function createPageSectionUpdateWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageSectionUpdateUri(Account $account, Website $website, Page $page, string $publicId): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections/'.$publicId;
}

function pageSectionUpdateListUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections';
}

function pageSectionUpdatePublishUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/publish';
}

function createPageSectionUpdatePage(Website $website, User $creator): Page
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

function createPageSectionUpdateTemplate(): SectionTemplate
{
    $type = SectionType::query()->create([
        'name' => 'Hero',
        'key' => 'hero-'.uniqid(),
        'content_schema' => ['type' => 'object'],
        'status' => SectionType::STATUS_ACTIVE,
    ]);

    return SectionTemplate::query()->create([
        'section_type_id' => $type->id,
        'name' => 'Centered Hero',
        'key' => 'hero-centered-'.uniqid(),
        'status' => SectionTemplate::STATUS_ACTIVE,
    ]);
}

function createPageSectionUpdateFixture(
    PageVersion $version,
    SectionTemplate $template,
    array $content = ['heading' => 'Hello'],
    array $sectionOverrides = [],
): PageSection {
    $section = PageSection::query()->create(array_merge([
        'page_version_id' => $version->id,
        'section_template_id' => $template->id,
        'sort_order' => 0,
        'is_visible' => true,
        'settings' => ['theme' => 'light', 'alignment' => 'left'],
    ], $sectionOverrides));

    PageSectionContent::query()->create([
        'page_section_id' => $section->id,
        'content' => $content,
    ]);

    return $section->refresh();
}

function simulatePageSectionUpdatePublished(Page $page, User $publisher): void
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

test('unauthenticated section patch is unauthorized', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($page->draftVersion, $template);

    test()->patchJson(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
        'is_visible' => false,
    ])->assertUnauthorized();
});

test('page update permission is required to patch section', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user, ['page.view']);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($page->draftVersion, $template);

    loginPageSectionUpdateUser($user);

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
        'is_visible' => false,
    ])->assertForbidden();
});

test('section patch validates request fields', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($page->draftVersion, $template);

    loginPageSectionUpdateUser($user);

    $uri = pageSectionUpdateUri($account, $website, $page, $section->public_id);

    statefulPatchPageSectionRaw($uri, '{}')
        ->assertUnprocessable();

    statefulPatchPageSectionRaw($uri, '{"content": {}}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    statefulPatchPageSectionRaw($uri, '{"settings": null}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings']);

    statefulPatchPageSectionRaw($uri, '{"settings": []}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings']);

    statefulPatchPageSectionRaw($uri, '{"settings": "dark"}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['settings']);

    statefulPatchPageSectionRaw($uri, '{"is_visible": "false"}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_visible']);

    statefulPatchPageSectionRaw($uri, '{"is_visible": 0}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_visible']);

    statefulPatchPageSectionRaw($uri, '{"settings": {"columns": ["left", "right"]}}')
        ->assertOk();

    statefulPatchPageSectionRaw($uri, '{"settings": {}}')
        ->assertOk();
});

test('section patch replaces settings document on unpublished page', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($page->draftVersion, $template);

    loginPageSectionUpdateUser($user);

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
        'settings' => ['alignment' => 'center'],
    ])
        ->assertOk()
        ->assertJsonPath('data.settings.alignment', 'center')
        ->assertJsonMissingPath('data.settings.theme');

    expect($section->refresh()->settings)->toBe(['alignment' => 'center'])
        ->and(PageVersion::query()->count())->toBe(1);
});

test('section patch updates visibility without changing settings or content', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($page->draftVersion, $template);

    loginPageSectionUpdateUser($user);

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
        'is_visible' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.is_visible', false)
        ->assertJsonPath('data.settings.theme', 'light')
        ->assertJsonPath('data.content.heading', 'Hello');

    expect($section->refresh()->is_visible)->toBeFalse()
        ->and($section->settings)->toBe(['theme' => 'light', 'alignment' => 'left'])
        ->and($section->content?->content)->toBe(['heading' => 'Hello']);
});

test('section patch updates settings and visibility atomically', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($page->draftVersion, $template);

    loginPageSectionUpdateUser($user);

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
        'settings' => ['alignment' => 'center'],
        'is_visible' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.settings.alignment', 'center')
        ->assertJsonPath('data.is_visible', false);

    $section->refresh();

    expect($section->settings)->toBe(['alignment' => 'center'])
        ->and($section->is_visible)->toBeFalse();
});

test('published section patch clones draft and preserves public id', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($versionOne, $template, ['heading' => 'Keep'], [
        'settings' => ['theme' => 'old'],
        'is_visible' => true,
    ]);

    $numericId = $section->id;
    $publicId = $section->public_id;

    simulatePageSectionUpdatePublished($page, $user);

    loginPageSectionUpdateUser($user);

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $publicId), [
        'settings' => ['theme' => 'new'],
        'is_visible' => false,
    ])->assertOk()->assertJsonPath('data.id', $publicId);

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();
    $cloned = PageSection::query()->where('page_version_id', $versionTwo->id)->wherePublicId($publicId)->firstOrFail();

    expect($cloned->id)->not->toBe($numericId)
        ->and($cloned->settings)->toBe(['theme' => 'new'])
        ->and($cloned->is_visible)->toBeFalse()
        ->and(PageSection::query()->find($numericId)?->settings)->toBe(['theme' => 'old'])
        ->and(PageSection::query()->find($numericId)?->is_visible)->toBeTrue()
        ->and(PageSectionContent::query()->where('page_section_id', $cloned->id)->first()?->content)
        ->toBe(['heading' => 'Keep'])
        ->and(PageSectionContent::query()->where('page_section_id', $numericId)->first()?->content)
        ->toBe(['heading' => 'Keep']);
});

test('published no-op section patch does not clone', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($versionOne, $template, sectionOverrides: [
        'settings' => ['alignment' => 'center'],
        'is_visible' => true,
    ]);

    simulatePageSectionUpdatePublished($page, $user);

    loginPageSectionUpdateUser($user);

    $updatedAt = $section->updated_at;

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
        'is_visible' => true,
    ])->assertOk();

    statefulPatchPageSectionRaw(
        pageSectionUpdateUri($account, $website, $page, $section->public_id),
        '{"settings":{"alignment":"center"}}',
    )->assertOk();

    statefulPatchPageSectionRaw(
        pageSectionUpdateUri($account, $website, $page, $section->public_id),
        '{"settings":{"alignment":"center"},"is_visible":true}',
    )->assertOk();

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($section->refresh()->updated_at->eq($updatedAt))->toBeTrue();
});

test('published settings array order change is meaningful', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($versionOne, $template, sectionOverrides: [
        'settings' => ['columns' => ['left', 'right']],
    ]);

    simulatePageSectionUpdatePublished($page, $user);

    loginPageSectionUpdateUser($user);

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
        'settings' => ['columns' => ['right', 'left']],
    ])->assertOk();

    expect(PageVersion::query()->count())->toBe(2);
});

test('section patch empty settings replaces document and serializes as object', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($page->draftVersion, $template);

    loginPageSectionUpdateUser($user);

    $response = statefulPatchPageSectionRaw(
        pageSectionUpdateUri($account, $website, $page, $section->public_id),
        '{"settings": {}}',
    )->assertOk();

    expect($response->getContent())->toContain('"settings":{}')
        ->and($section->refresh()->settings)->toBe([]);
});

test('section patch returns not found for invalid section identity', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($page->draftVersion, $template);

    simulatePageSectionUpdatePublished($page, $user);

    $versionTwo = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 2,
        'name' => 'About',
        'slug' => 'about',
        'created_by' => $user->id,
    ]);

    $page->assignDraftVersion($versionTwo);

    loginPageSectionUpdateUser($user);

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
        'is_visible' => false,
    ])->assertNotFound();

    $section->delete();

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
        'is_visible' => false,
    ])->assertNotFound();

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, 'not-a-uuid'), [
        'is_visible' => false,
    ])->assertNotFound();
});

test('section patch rolls back clone when section persistence fails', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($versionOne, $template);

    simulatePageSectionUpdatePublished($page, $user);

    loginPageSectionUpdateUser($user);

    PageSection::updating(function (): void {
        throw new RuntimeException('Simulated section persistence failure');
    });

    try {
        statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $section->public_id), [
            'is_visible' => false,
        ])->assertStatus(500);
    } finally {
        PageSection::flushEventListeners();
    }

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($section->refresh()->is_visible)->toBeTrue()
        ->and($section->content?->content)->toBe(['heading' => 'Hello']);
});

test('section patch appears on sections get and survives publish', function () {
    $user = createPageSectionUpdateUser();
    $account = attachPageSectionUpdateMembership($user);
    $website = createPageSectionUpdateWebsite($account);
    $page = createPageSectionUpdatePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionUpdateTemplate();
    $section = createPageSectionUpdateFixture($versionOne, $template);

    simulatePageSectionUpdatePublished($page, $user);

    loginPageSectionUpdateUser($user);

    $publicId = $section->public_id;

    statefulPatchPageSection(pageSectionUpdateUri($account, $website, $page, $publicId), [
        'settings' => ['theme' => 'draft'],
        'is_visible' => false,
    ])->assertOk();

    statefulGetPageSectionUpdateList(pageSectionUpdateListUri($account, $website, $page))
        ->assertOk()
        ->assertJsonPath('data.0.id', $publicId)
        ->assertJsonPath('data.0.settings.theme', 'draft')
        ->assertJsonPath('data.0.is_visible', false);

    statefulPostPageSectionUpdatePublish(pageSectionUpdatePublishUri($account, $website, $page))->assertOk();

    $page->refresh();
    $publishedSection = PageSection::query()
        ->where('page_version_id', $page->published_version_id)
        ->wherePublicId($publicId)
        ->firstOrFail();

    expect($publishedSection->settings)->toBe(['theme' => 'draft'])
        ->and($publishedSection->is_visible)->toBeFalse();
});
