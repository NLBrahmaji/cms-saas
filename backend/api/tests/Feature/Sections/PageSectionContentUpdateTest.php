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

function pageSectionContentOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageSectionContentCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPatchPageSectionContent(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageSectionContentOriginHeaders())->patchJson($uri, $data);

    syncPageSectionContentCookies($response);

    return $response;
}

function statefulPatchPageSectionContentRaw(string $uri, string $json): TestResponse
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

    syncPageSectionContentCookies($response);

    return $response;
}

function statefulGetPageSectionContent(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionContentOriginHeaders())->getJson($uri);

    syncPageSectionContentCookies($response);

    return $response;
}

function statefulPostPageSectionContentPublish(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionContentOriginHeaders())->postJson($uri);

    syncPageSectionContentCookies($response);

    return $response;
}

function loginPageSectionContentUser(User $user): void
{
    test()->withHeaders(pageSectionContentOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createPageSectionContentUser(): User
{
    return User::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada-section-content-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachPageSectionContentMembership(User $user, array $permissions = AccountPermissionSeeder::PERMISSIONS): Account
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

function createPageSectionContentWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageSectionsContentUri(Account $account, Website $website, Page $page, string $publicId): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections/'.$publicId.'/content';
}

function pageSectionsListUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections';
}

function pageSectionContentPublishUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/publish';
}

function createPageSectionContentPage(Website $website, User $creator): Page
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

function createPageSectionContentTemplate(): SectionTemplate
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

function createPageSectionWithContent(PageVersion $version, SectionTemplate $template, array $content, array $sectionOverrides = []): PageSection
{
    $section = PageSection::query()->create(array_merge([
        'page_version_id' => $version->id,
        'section_template_id' => $template->id,
        'sort_order' => 0,
        'is_visible' => true,
        'settings' => ['theme' => 'dark'],
    ], $sectionOverrides));

    PageSectionContent::query()->create([
        'page_section_id' => $section->id,
        'content' => $content,
    ]);

    return $section->refresh();
}

function simulatePageSectionContentPublished(Page $page, User $publisher): void
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

test('unauthenticated section content patch is unauthorized', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($page->draftVersion, $template, ['heading' => 'Hi']);

    test()->patchJson(pageSectionsContentUri($account, $website, $page, $section->public_id), [
        'content' => ['heading' => 'Nope'],
    ])->assertUnauthorized();
});

test('page update permission is required to patch section content', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user, ['page.view']);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($page->draftVersion, $template, ['heading' => 'Hi']);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $section->public_id), [
        'content' => ['heading' => 'Nope'],
    ])->assertForbidden();
});

test('section content patch validates request document shape', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($page->draftVersion, $template, ['heading' => 'Hi']);

    loginPageSectionContentUser($user);

    $uri = pageSectionsContentUri($account, $website, $page, $section->public_id);

    statefulPatchPageSectionContent($uri, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    statefulPatchPageSectionContentRaw($uri, '{"content": null}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    statefulPatchPageSectionContentRaw($uri, '{"content": "text"}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    statefulPatchPageSectionContentRaw($uri, '{"content": 1}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    statefulPatchPageSectionContentRaw($uri, '{"content": true}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    statefulPatchPageSectionContentRaw($uri, '{"content": []}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    statefulPatchPageSectionContentRaw($uri, '{"content": {}, "extra": true}')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['extra']);

    statefulPatchPageSectionContentRaw($uri, '{"content": {"heading": "Ok", "items": [{"title": "A"}]}}')
        ->assertOk()
        ->assertJsonPath('data.content.heading', 'Ok')
        ->assertJsonPath('data.content.items.0.title', 'A');
});

test('section content patch replaces document on unpublished page', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $version = $page->draftVersion;
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($version, $template, [
        'heading' => 'Hello',
        'description' => 'Old',
        'button' => ['label' => 'Learn more'],
    ]);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $section->public_id), [
        'content' => ['heading' => 'Hello again'],
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $section->public_id)
        ->assertJsonPath('data.content.heading', 'Hello again')
        ->assertJsonMissingPath('data.content.description')
        ->assertJsonPath('data.settings.theme', 'dark');

    expect(PageVersion::query()->count())->toBe(1)
        ->and(PageSectionContent::query()->first()?->content)->toBe(['heading' => 'Hello again'])
        ->and($section->refresh()->settings)->toBe(['theme' => 'dark']);
});

test('published meaningful content patch clones draft and preserves stable public id', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($versionOne, $template, ['heading' => 'Old']);

    $numericId = $section->id;
    $publicId = $section->public_id;

    simulatePageSectionContentPublished($page, $user);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $publicId), [
        'content' => ['heading' => 'New'],
    ])->assertOk()->assertJsonPath('data.id', $publicId);

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect($page->draft_version_id)->toBe($versionTwo->id)
        ->and($page->published_version_id)->toBe($versionOne->id)
        ->and(PageSectionContent::query()->where('page_section_id', $numericId)->first()?->content)
        ->toBe(['heading' => 'Old']);

    $clonedSection = PageSection::query()->where('page_version_id', $versionTwo->id)->wherePublicId($publicId)->firstOrFail();

    expect($clonedSection->id)->not->toBe($numericId)
        ->and(PageSectionContent::query()->where('page_section_id', $clonedSection->id)->first()?->content)
        ->toBe(['heading' => 'New']);
});

test('published no-op content patch does not clone', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($versionOne, $template, ['heading' => 'Welcome']);

    simulatePageSectionContentPublished($page, $user);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $section->public_id), [
        'content' => ['heading' => 'Welcome'],
    ])->assertOk();

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($versionOne->id)
        ->and($page->published_version_id)->toBe($versionOne->id);
});

test('published no-op ignores object key order when comparing documents', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($versionOne, $template, [
        'heading' => 'Hello',
        'meta' => ['a' => 1, 'b' => 2],
    ]);

    simulatePageSectionContentPublished($page, $user);

    loginPageSectionContentUser($user);

    $contentRow = PageSectionContent::query()->where('page_section_id', $section->id)->firstOrFail();
    $updatedAt = $contentRow->updated_at;

    statefulPatchPageSectionContentRaw(
        pageSectionsContentUri($account, $website, $page, $section->public_id),
        '{"content":{"meta":{"b":2,"a":1},"heading":"Hello"}}',
    )
        ->assertOk()
        ->assertJsonPath('data.content.heading', 'Hello')
        ->assertJsonPath('data.content.meta.a', 1)
        ->assertJsonPath('data.content.meta.b', 2);

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($versionOne->id)
        ->and($page->published_version_id)->toBe($versionOne->id)
        ->and($contentRow->refresh()->updated_at->eq($updatedAt))->toBeTrue()
        ->and($contentRow->content)->toBe([
            'heading' => 'Hello',
            'meta' => ['a' => 1, 'b' => 2],
        ]);
});

test('published content patch with reordered array items is a meaningful change', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($versionOne, $template, [
        'items' => [
            ['title' => 'A'],
            ['title' => 'B'],
        ],
    ]);

    $numericId = $section->id;
    $publicId = $section->public_id;

    simulatePageSectionContentPublished($page, $user);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $publicId), [
        'content' => [
            'items' => [
                ['title' => 'B'],
                ['title' => 'A'],
            ],
        ],
    ])->assertOk();

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();
    $clonedSection = PageSection::query()->where('page_version_id', $versionTwo->id)->wherePublicId($publicId)->firstOrFail();

    expect($page->draft_version_id)->toBe($versionTwo->id)
        ->and($page->published_version_id)->toBe($versionOne->id)
        ->and(PageSectionContent::query()->where('page_section_id', $numericId)->first()?->content)
        ->toBe([
            'items' => [
                ['title' => 'A'],
                ['title' => 'B'],
            ],
        ])
        ->and(PageSectionContent::query()->where('page_section_id', $clonedSection->id)->first()?->content)
        ->toBe([
            'items' => [
                ['title' => 'B'],
                ['title' => 'A'],
            ],
        ]);
});

test('section content patch on existing ahead draft does not create another version', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($versionOne, $template, ['heading' => 'Old']);

    simulatePageSectionContentPublished($page, $user);

    $versionTwo = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionOne, $user);
    $page->assignDraftVersion($versionTwo);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $section->public_id), [
        'content' => ['heading' => 'Draft edit'],
    ])->assertOk();

    expect(PageVersion::query()->count())->toBe(2);
});

test('empty content object is valid and encodes as json object', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($page->draftVersion, $template, ['heading' => 'Remove me']);

    loginPageSectionContentUser($user);

    $response = statefulPatchPageSectionContentRaw(
        pageSectionsContentUri($account, $website, $page, $section->public_id),
        '{"content": {}}',
    )->assertOk();

    expect($response->getContent())->toContain('"content":{}')
        ->and(PageSectionContent::query()->first()?->content)->toBe([]);
});

test('missing content row with empty object is a no-op', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $template = createPageSectionContentTemplate();

    $section = PageSection::query()->create([
        'page_version_id' => $page->draftVersion->id,
        'section_template_id' => $template->id,
        'sort_order' => 0,
    ]);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContentRaw(
        pageSectionsContentUri($account, $website, $page, $section->public_id),
        '{"content": {}}',
    )->assertOk();

    expect(PageSectionContent::query()->where('page_section_id', $section->id)->exists())->toBeFalse();
});

test('missing content row with non-empty object creates content after clone when published', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionContentTemplate();

    $section = PageSection::query()->create([
        'page_version_id' => $versionOne->id,
        'section_template_id' => $template->id,
        'sort_order' => 0,
    ]);

    simulatePageSectionContentPublished($page, $user);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $section->public_id), [
        'content' => ['heading' => 'Added'],
    ])->assertOk();

    expect(PageVersion::query()->count())->toBe(2)
        ->and(PageSectionContent::query()->where('page_section_id', $section->id)->exists())->toBeFalse();

    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();
    $cloned = PageSection::query()->where('page_version_id', $versionTwo->id)->wherePublicId($section->public_id)->firstOrFail();

    expect(PageSectionContent::query()->where('page_section_id', $cloned->id)->first()?->content)
        ->toBe(['heading' => 'Added']);
});

test('section content patch returns not found for historical-only public id', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($versionOne, $template, ['heading' => 'Published only']);

    simulatePageSectionContentPublished($page, $user);

    $versionTwo = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 2,
        'name' => 'About',
        'slug' => 'about',
        'created_by' => $user->id,
    ]);

    $page->assignDraftVersion($versionTwo);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $section->public_id), [
        'content' => ['heading' => 'Nope'],
    ])->assertNotFound();

    expect(PageVersion::query()->count())->toBe(2);
});

test('section content patch returns not found for soft deleted draft section', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($page->draftVersion, $template, ['heading' => 'Hi']);
    $section->delete();

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $section->public_id), [
        'content' => ['heading' => 'Nope'],
    ])->assertNotFound();
});

test('section content patch returns not found for malformed or unknown public id', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $template = createPageSectionContentTemplate();
    createPageSectionWithContent($page->draftVersion, $template, ['heading' => 'Hi']);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, 'not-a-uuid'), [
        'content' => ['heading' => 'Nope'],
    ])->assertNotFound();

    statefulPatchPageSectionContent(
        pageSectionsContentUri($account, $website, $page, '550e8400-e29b-41d4-a716-446655440000'),
        ['content' => ['heading' => 'Nope']],
    )->assertNotFound();
});

test('section content patch returns not found when public id belongs to another page', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $pageA = createPageSectionContentPage($website, $user);
    $pageB = createPageSectionContentPage($website, $user);
    $template = createPageSectionContentTemplate();
    $sectionOnB = createPageSectionWithContent($pageB->draftVersion, $template, ['heading' => 'Other']);

    loginPageSectionContentUser($user);

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $pageA, $sectionOnB->public_id), [
        'content' => ['heading' => 'Nope'],
    ])->assertNotFound();
});

test('section content patch appears on sections get and survives publish', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($versionOne, $template, ['heading' => 'Old']);

    simulatePageSectionContentPublished($page, $user);

    loginPageSectionContentUser($user);

    $publicId = $section->public_id;

    statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $publicId), [
        'content' => ['heading' => 'Draft'],
    ])->assertOk();

    statefulGetPageSectionContent(pageSectionsListUri($account, $website, $page))
        ->assertOk()
        ->assertJsonPath('data.0.id', $publicId)
        ->assertJsonPath('data.0.content.heading', 'Draft');

    statefulPostPageSectionContentPublish(pageSectionContentPublishUri($account, $website, $page))->assertOk();

    $page->refresh();

    expect($page->draft_version_id)->toBe($page->published_version_id)
        ->and(PageSection::query()->where('page_version_id', $page->draft_version_id)->wherePublicId($publicId)->exists())->toBeTrue();
});

test('section content patch rolls back clone when content persistence fails', function () {
    $user = createPageSectionContentUser();
    $account = attachPageSectionContentMembership($user);
    $website = createPageSectionContentWebsite($account);
    $page = createPageSectionContentPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionContentTemplate();
    $section = createPageSectionWithContent($versionOne, $template, ['heading' => 'Old']);

    simulatePageSectionContentPublished($page, $user);

    loginPageSectionContentUser($user);

    PageSectionContent::updating(function (): void {
        throw new RuntimeException('Simulated content persistence failure');
    });

    try {
        statefulPatchPageSectionContent(pageSectionsContentUri($account, $website, $page, $section->public_id), [
            'content' => ['heading' => 'New'],
        ])->assertStatus(500);
    } finally {
        PageSectionContent::flushEventListeners();
    }

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($versionOne->id)
        ->and($page->published_version_id)->toBe($versionOne->id)
        ->and(PageSectionContent::query()->first()?->content)->toBe(['heading' => 'Old']);
});
