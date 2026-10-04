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
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function pageSectionDeleteOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageSectionDeleteCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulDeletePageSection(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionDeleteOriginHeaders())->deleteJson($uri);

    syncPageSectionDeleteCookies($response);

    return $response;
}

function statefulGetPageSectionDeleteList(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionDeleteOriginHeaders())->getJson($uri);

    syncPageSectionDeleteCookies($response);

    return $response;
}

function statefulPostPageSectionDelete(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageSectionDeleteOriginHeaders())->postJson($uri, $data);

    syncPageSectionDeleteCookies($response);

    return $response;
}

function statefulPutPageSectionDeleteReorder(string $uri, array $data): TestResponse
{
    $response = test()->withHeaders(pageSectionDeleteOriginHeaders())->putJson($uri, $data);

    syncPageSectionDeleteCookies($response);

    return $response;
}

function loginPageSectionDeleteUser(User $user): void
{
    test()->withHeaders(pageSectionDeleteOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createPageSectionDeleteUser(): User
{
    return User::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada-section-delete-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachPageSectionDeleteMembership(User $user, array $permissions = AccountPermissionSeeder::PERMISSIONS): Account
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

function createPageSectionDeleteWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageSectionDeleteUri(Account $account, Website $website, Page $page, string $publicId): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections/'.$publicId;
}

function pageSectionDeleteListUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections';
}

function pageSectionDeleteReorderUri(Account $account, Website $website, Page $page): string
{
    return pageSectionDeleteListUri($account, $website, $page).'/order';
}

function pageSectionDeletePublishUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/publish';
}

function createPageSectionDeletePage(Website $website, User $creator): Page
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

function createPageSectionDeleteTemplate(): SectionTemplate
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

function createPageSectionDeleteRow(
    PageVersion $version,
    SectionTemplate $template,
    int $sortOrder,
    array $overrides = [],
): PageSection {
    $section = PageSection::query()->create(array_merge([
        'page_version_id' => $version->id,
        'section_template_id' => $template->id,
        'sort_order' => $sortOrder,
        'is_visible' => true,
        'settings' => ['theme' => 'dark'],
    ], $overrides));

    PageSectionContent::query()->create([
        'page_section_id' => $section->id,
        'content' => ['heading' => 'Hello-'.$section->id],
    ]);

    return $section->refresh();
}

function simulatePageSectionDeletePublished(Page $page, User $publisher): void
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

test('unauthenticated section delete is unauthorized', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();
    $section = createPageSectionDeleteRow($page->draftVersion, $template, 0);

    test()->deleteJson(pageSectionDeleteUri($account, $website, $page, $section->public_id))
        ->assertUnauthorized();
});

test('page update permission is required to delete sections', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user, ['page.view']);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();
    $section = createPageSectionDeleteRow($page->draftVersion, $template, 0);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $section->public_id))
        ->assertForbidden();
});

test('section delete on unpublished page soft deletes without cloning', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();

    $a = createPageSectionDeleteRow($page->draftVersion, $template, 0);
    $b = createPageSectionDeleteRow($page->draftVersion, $template, 1);
    $c = createPageSectionDeleteRow($page->draftVersion, $template, 2);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $b->public_id))
        ->assertNoContent();

    expect(PageVersion::query()->count())->toBe(1)
        ->and(PageSection::query()->where('page_version_id', $page->draft_version_id)->count())->toBe(2)
        ->and(PageSection::withTrashed()->find($b->id)?->deleted_at)->not->toBeNull()
        ->and($a->refresh()->sort_order)->toBe(0)
        ->and($c->refresh()->sort_order)->toBe(2);
});

test('published section delete clones draft and preserves published snapshot', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionDeleteTemplate();

    $a = createPageSectionDeleteRow($versionOne, $template, 0);
    $b = createPageSectionDeleteRow($versionOne, $template, 1);
    $c = createPageSectionDeleteRow($versionOne, $template, 2);

    $bNumericId = $b->id;
    $bPublicId = $b->public_id;
    $bContent = $b->content?->content;

    simulatePageSectionDeletePublished($page, $user);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $bPublicId))
        ->assertNoContent();

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect($page->published_version_id)->toBe($versionOne->id)
        ->and($page->draft_version_id)->toBe($versionTwo->id)
        ->and(PageSection::query()->find($bNumericId)?->deleted_at)->toBeNull()
        ->and(PageSection::query()->where('page_version_id', $versionOne->id)->count())->toBe(3);

    $clonedB = PageSection::withTrashed()
        ->where('page_version_id', $versionTwo->id)
        ->wherePublicId($bPublicId)
        ->firstOrFail();

    expect($clonedB->id)->not->toBe($bNumericId)
        ->and($clonedB->deleted_at)->not->toBeNull()
        ->and($clonedB->sort_order)->toBe(1)
        ->and($clonedB->settings)->toBe(['theme' => 'dark'])
        ->and($clonedB->is_visible)->toBeTrue()
        ->and(PageSectionContent::query()->where('page_section_id', $clonedB->id)->first()?->content)
        ->toBe($bContent);

    statefulGetPageSectionDeleteList(pageSectionDeleteListUri($account, $website, $page))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $a->public_id)
        ->assertJsonPath('data.1.id', $c->public_id);
});

test('ahead draft section delete does not create another version', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionDeleteTemplate();

    $a = createPageSectionDeleteRow($versionOne, $template, 0);
    $b = createPageSectionDeleteRow($versionOne, $template, 1);

    simulatePageSectionDeletePublished($page, $user);

    $versionTwo = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionOne, $user);
    $page->assignDraftVersion($versionTwo);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $b->public_id))
        ->assertNoContent();

    expect(PageVersion::query()->count())->toBe(2)
        ->and(PageSection::query()->where('page_version_id', $versionOne->id)->count())->toBe(2)
        ->and(PageSection::withTrashed()
            ->where('page_version_id', $versionTwo->id)
            ->wherePublicId($b->public_id)
            ->first()?->deleted_at)->not->toBeNull();
});

test('repeated section delete returns not found', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();
    $section = createPageSectionDeleteRow($page->draftVersion, $template, 0);

    loginPageSectionDeleteUser($user);

    $uri = pageSectionDeleteUri($account, $website, $page, $section->public_id);

    statefulDeletePageSection($uri)->assertNoContent();
    statefulDeletePageSection($uri)->assertNotFound();

    expect(PageVersion::query()->count())->toBe(1);
});

test('section delete returns not found for historical-only section on ahead draft', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionDeleteTemplate();

    $b = createPageSectionDeleteRow($versionOne, $template, 1);

    simulatePageSectionDeletePublished($page, $user);

    $versionTwo = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionOne, $user);
    $page->assignDraftVersion($versionTwo);

    $draftB = PageSection::query()
        ->where('page_version_id', $versionTwo->id)
        ->wherePublicId($b->public_id)
        ->firstOrFail();
    $draftB->delete();

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $b->public_id))
        ->assertNotFound();
});

test('section delete returns not found for malformed unknown and wrong page ids', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $otherPage = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();
    $section = createPageSectionDeleteRow($page->draftVersion, $template, 0);
    $other = createPageSectionDeleteRow($otherPage->draftVersion, $template, 0);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, 'not-a-uuid'))
        ->assertNotFound();

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, (string) Str::uuid()))
        ->assertNotFound();

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $other->public_id))
        ->assertNotFound();
});

test('section delete returns not found for wrong account or website nesting', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $otherUser = createPageSectionDeleteUser();
    $otherAccount = attachPageSectionDeleteMembership($otherUser);
    $website = createPageSectionDeleteWebsite($account);
    $otherWebsite = createPageSectionDeleteWebsite($otherAccount);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();
    $section = createPageSectionDeleteRow($page->draftVersion, $template, 0);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection('/v1/accounts/'.$otherAccount->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections/'.$section->public_id)
        ->assertNotFound();

    statefulDeletePageSection('/v1/accounts/'.$account->id.'/websites/'.$otherWebsite->id.'/pages/'.$page->id.'/sections/'.$section->public_id)
        ->assertNotFound();
});

test('section delete preserves content row and metadata except soft delete timestamp', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();
    $section = createPageSectionDeleteRow($page->draftVersion, $template, 3, [
        'is_visible' => false,
        'settings' => ['layout' => 'wide'],
    ]);

    $publicId = $section->public_id;
    $contentId = $section->content?->id;
    $templateId = $section->section_template_id;

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $publicId))
        ->assertNoContent();

    $trashed = PageSection::withTrashed()->find($section->id);

    expect($trashed?->public_id)->toBe($publicId)
        ->and($trashed?->section_template_id)->toBe($templateId)
        ->and($trashed?->sort_order)->toBe(3)
        ->and($trashed?->settings)->toBe(['layout' => 'wide'])
        ->and($trashed?->is_visible)->toBeFalse()
        ->and(PageSectionContent::query()->find($contentId)?->content)->toBe(['heading' => 'Hello-'.$section->id]);
});

test('add section after deleting last active section uses active max plus one', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();

    $a = createPageSectionDeleteRow($page->draftVersion, $template, 0);
    $b = createPageSectionDeleteRow($page->draftVersion, $template, 1);
    $c = createPageSectionDeleteRow($page->draftVersion, $template, 2);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $c->public_id))
        ->assertNoContent();

    statefulPostPageSectionDelete(pageSectionDeleteListUri($account, $website, $page), [
        'section_template_id' => $template->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 2);
});

test('add section after deleting middle section appends after remaining active max', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();

    $a = createPageSectionDeleteRow($page->draftVersion, $template, 0);
    $b = createPageSectionDeleteRow($page->draftVersion, $template, 1);
    $c = createPageSectionDeleteRow($page->draftVersion, $template, 2);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $b->public_id))
        ->assertNoContent();

    statefulPostPageSectionDelete(pageSectionDeleteListUri($account, $website, $page), [
        'section_template_id' => $template->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 3);

    expect($a->refresh()->sort_order)->toBe(0)
        ->and($c->refresh()->sort_order)->toBe(2);
});

test('reorder after section delete requires only active sections', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();

    $a = createPageSectionDeleteRow($page->draftVersion, $template, 0);
    $b = createPageSectionDeleteRow($page->draftVersion, $template, 1);
    $c = createPageSectionDeleteRow($page->draftVersion, $template, 2);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $b->public_id))
        ->assertNoContent();

    $reorderUri = pageSectionDeleteReorderUri($account, $website, $page);

    statefulPutPageSectionDeleteReorder($reorderUri, [
        'section_ids' => [$a->public_id, $c->public_id],
    ])->assertOk();

    statefulPutPageSectionDeleteReorder($reorderUri, [
        'section_ids' => [$a->public_id, $b->public_id, $c->public_id],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_ids']);
});

test('publish after section delete promotes draft without deleted section', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionDeleteTemplate();

    $a = createPageSectionDeleteRow($versionOne, $template, 0);
    $b = createPageSectionDeleteRow($versionOne, $template, 1);
    $c = createPageSectionDeleteRow($versionOne, $template, 2);

    simulatePageSectionDeletePublished($page, $user);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $b->public_id))
        ->assertNoContent();

    statefulPostPageSectionDelete(pageSectionDeletePublishUri($account, $website, $page))
        ->assertOk();

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    expect($page->draft_version_id)->toBe($versionTwo->id)
        ->and($page->published_version_id)->toBe($versionTwo->id)
        ->and(PageSection::query()->where('page_version_id', $versionOne->id)->count())->toBe(3)
        ->and(PageSection::query()->where('page_version_id', $versionTwo->id)->count())->toBe(2);

    statefulGetPageSectionDeleteList(pageSectionDeleteListUri($account, $website, $page))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $a->public_id)
        ->assertJsonPath('data.1.id', $c->public_id);

    expect(PageSection::withTrashed()
        ->where('page_version_id', $versionTwo->id)
        ->wherePublicId($b->public_id)
        ->first()?->deleted_at)->not->toBeNull()
        ->and(PageSection::query()->where('page_version_id', $versionOne->id)->wherePublicId($b->public_id)->exists())->toBeTrue();
});

test('section delete rolls back clone when soft delete fails', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionDeleteTemplate();

    $b = createPageSectionDeleteRow($versionOne, $template, 1);
    $contentBefore = $b->content?->content;

    simulatePageSectionDeletePublished($page, $user);

    loginPageSectionDeleteUser($user);

    PageSection::deleting(function (): void {
        throw new RuntimeException('Simulated section delete failure');
    });

    try {
        statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $b->public_id))
            ->assertStatus(500);
    } finally {
        PageSection::flushEventListeners();
    }

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($page->draft_version_id)->toBe($versionOne->id)
        ->and($page->published_version_id)->toBe($versionOne->id)
        ->and(PageSection::query()->find($b->id)?->deleted_at)->toBeNull()
        ->and(PageSectionContent::query()->where('page_section_id', $b->id)->first()?->content)
        ->toBe($contentBefore);
});

test('section delete fails when draft pointer is corrupt', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $template = createPageSectionDeleteTemplate();
    $section = createPageSectionDeleteRow($page->draftVersion, $template, 0);

    $page->update(['draft_version_id' => null]);

    loginPageSectionDeleteUser($user);

    statefulDeletePageSection(pageSectionDeleteUri($account, $website, $page, $section->public_id))
        ->assertStatus(500);
});

test('snapshot cloner does not resurrect soft-deleted sections from ahead draft', function () {
    $user = createPageSectionDeleteUser();
    $account = attachPageSectionDeleteMembership($user);
    $website = createPageSectionDeleteWebsite($account);
    $page = createPageSectionDeletePage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionDeleteTemplate();

    $b = createPageSectionDeleteRow($versionOne, $template, 0);

    simulatePageSectionDeletePublished($page, $user);

    $versionTwo = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionOne, $user);
    $page->assignDraftVersion($versionTwo);

    $draftB = PageSection::query()
        ->where('page_version_id', $versionTwo->id)
        ->wherePublicId($b->public_id)
        ->firstOrFail();
    $draftB->delete();

    $versionThree = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionTwo, $user);

    expect(PageSection::query()->where('page_version_id', $versionThree->id)->count())->toBe(0);
});
