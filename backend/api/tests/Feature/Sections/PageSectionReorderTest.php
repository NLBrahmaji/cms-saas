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

function pageSectionReorderOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageSectionReorderCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPutPageSectionReorder(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageSectionReorderOriginHeaders())->putJson($uri, $data);

    syncPageSectionReorderCookies($response);

    return $response;
}

function statefulGetPageSectionReorder(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionReorderOriginHeaders())->getJson($uri);

    syncPageSectionReorderCookies($response);

    return $response;
}

function statefulPostPageSectionReorder(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageSectionReorderOriginHeaders())->postJson($uri, $data);

    syncPageSectionReorderCookies($response);

    return $response;
}

function loginPageSectionReorderUser(User $user): void
{
    test()->withHeaders(pageSectionReorderOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function createPageSectionReorderUser(): User
{
    return User::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada-section-reorder-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function attachPageSectionReorderMembership(User $user, array $permissions = AccountPermissionSeeder::PERMISSIONS): Account
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

function createPageSectionReorderWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageSectionReorderUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections/order';
}

function pageSectionReorderListUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections';
}

function pageSectionReorderAddUri(Account $account, Website $website, Page $page): string
{
    return pageSectionReorderListUri($account, $website, $page);
}

function createPageSectionReorderPage(Website $website, User $creator): Page
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

function createPageSectionReorderTemplate(): SectionTemplate
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

function createPageSectionReorderRow(
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

function simulatePageSectionReorderPublished(Page $page, User $publisher): void
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

test('unauthenticated section reorder is unauthorized', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);

    test()->putJson(pageSectionReorderUri($account, $website, $page), ['section_ids' => []])
        ->assertUnauthorized();
});

test('page update permission is required to reorder sections', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user, ['page.view']);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);

    loginPageSectionReorderUser($user);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), ['section_ids' => []])
        ->assertForbidden();
});

test('section reorder validates request structure', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $template = createPageSectionReorderTemplate();
    $section = createPageSectionReorderRow($page->draftVersion, $template, 0);

    loginPageSectionReorderUser($user);

    $uri = pageSectionReorderUri($account, $website, $page);

    statefulPutPageSectionReorder($uri, ['extra' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['extra']);

    statefulPutPageSectionReorder($uri, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_ids']);

    statefulPutPageSectionReorder($uri, ['section_ids' => ['not-a-uuid']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_ids.0']);

    statefulPutPageSectionReorder($uri, ['section_ids' => [$section->public_id, $section->public_id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_ids']);
});

test('section reorder on unpublished page applies dense order', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($page->draftVersion, $template, 0);
    $b = createPageSectionReorderRow($page->draftVersion, $template, 1);
    $c = createPageSectionReorderRow($page->draftVersion, $template, 2);

    loginPageSectionReorderUser($user);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [$c->public_id, $a->public_id, $b->public_id],
    ])
        ->assertOk()
        ->assertJsonPath('data.0.id', $c->public_id)
        ->assertJsonPath('data.1.id', $a->public_id)
        ->assertJsonPath('data.2.id', $b->public_id);

    expect($c->refresh()->sort_order)->toBe(0)
        ->and($a->refresh()->sort_order)->toBe(1)
        ->and($b->refresh()->sort_order)->toBe(2)
        ->and(PageVersion::query()->count())->toBe(1);
});

test('published section reorder clones draft and preserves public ids', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($versionOne, $template, 0);
    $b = createPageSectionReorderRow($versionOne, $template, 1);

    $aNumeric = $a->id;
    $bNumeric = $b->id;

    simulatePageSectionReorderPublished($page, $user);

    loginPageSectionReorderUser($user);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [$b->public_id, $a->public_id],
    ])->assertOk();

    $page->refresh();
    $versionTwo = PageVersion::query()->where('version', 2)->firstOrFail();

    $clonedA = PageSection::query()->where('page_version_id', $versionTwo->id)->wherePublicId($a->public_id)->firstOrFail();
    $clonedB = PageSection::query()->where('page_version_id', $versionTwo->id)->wherePublicId($b->public_id)->firstOrFail();

    expect($page->published_version_id)->toBe($versionOne->id)
        ->and($clonedA->id)->not->toBe($aNumeric)
        ->and($clonedB->id)->not->toBe($bNumeric)
        ->and($clonedB->sort_order)->toBe(0)
        ->and($clonedA->sort_order)->toBe(1)
        ->and(PageSection::query()->find($aNumeric)?->sort_order)->toBe(0)
        ->and(PageSection::query()->find($bNumeric)?->sort_order)->toBe(1);
});

test('published section reorder no-op does not clone or rewrite sort gaps', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($versionOne, $template, 0);
    $b = createPageSectionReorderRow($versionOne, $template, 5);
    $c = createPageSectionReorderRow($versionOne, $template, 20);

    simulatePageSectionReorderPublished($page, $user);

    loginPageSectionReorderUser($user);

    $updatedAt = $a->updated_at;

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [$a->public_id, $b->public_id, $c->public_id],
    ])->assertOk();

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($a->refresh()->sort_order)->toBe(0)
        ->and($b->refresh()->sort_order)->toBe(5)
        ->and($c->refresh()->sort_order)->toBe(20)
        ->and($a->updated_at->eq($updatedAt))->toBeTrue();
});

test('section reorder uses sort order then id for effective ordering', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $template = createPageSectionReorderTemplate();

    $first = createPageSectionReorderRow($page->draftVersion, $template, 1);
    $second = createPageSectionReorderRow($page->draftVersion, $template, 1);

    $effective = PageSection::query()
        ->where('page_version_id', $page->draft_version_id)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->pluck('public_id')
        ->all();

    loginPageSectionReorderUser($user);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => $effective,
    ])->assertOk();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($first->refresh()->sort_order)->toBe(1)
        ->and($second->refresh()->sort_order)->toBe(1);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [$second->public_id, $first->public_id],
    ])->assertOk();

    expect($second->refresh()->sort_order)->toBe(0)
        ->and($first->refresh()->sort_order)->toBe(1);
});

test('section reorder rejects invalid collection membership', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $otherPage = createPageSectionReorderPage($website, $user);
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($page->draftVersion, $template, 0);
    $b = createPageSectionReorderRow($page->draftVersion, $template, 1);
    $other = createPageSectionReorderRow($otherPage->draftVersion, $template, 0);

    $deleted = createPageSectionReorderRow($page->draftVersion, $template, 2);
    $deleted->delete();

    loginPageSectionReorderUser($user);

    $uri = pageSectionReorderUri($account, $website, $page);

    statefulPutPageSectionReorder($uri, ['section_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_ids']);

    statefulPutPageSectionReorder($uri, ['section_ids' => [$a->public_id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_ids']);

    statefulPutPageSectionReorder($uri, ['section_ids' => [$a->public_id, $b->public_id, $other->public_id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_ids']);

    statefulPutPageSectionReorder($uri, ['section_ids' => [$a->public_id, $b->public_id, $deleted->public_id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_ids']);
});

test('section reorder rejects historical-only section ids on ahead draft', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($page->draftVersion, $template, 0);
    $b = createPageSectionReorderRow($page->draftVersion, $template, 1);

    simulatePageSectionReorderPublished($page, $user);

    $versionTwo = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 2,
        'name' => 'About',
        'slug' => 'about',
        'created_by' => $user->id,
    ]);

    $page->assignDraftVersion($versionTwo);

    loginPageSectionReorderUser($user);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [$a->public_id, $b->public_id],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['section_ids']);
});

test('empty section collection reorder is a no-op', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);

    loginPageSectionReorderUser($user);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [],
    ])
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('section reorder preserves settings and content', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($page->draftVersion, $template, 0);
    $b = createPageSectionReorderRow($page->draftVersion, $template, 1);

    $aContent = $a->content?->content;
    $bContent = $b->content?->content;

    loginPageSectionReorderUser($user);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [$b->public_id, $a->public_id],
    ])->assertOk();

    expect($a->refresh()->settings)->toBe(['theme' => 'dark'])
        ->and($b->refresh()->settings)->toBe(['theme' => 'dark'])
        ->and($a->content?->content)->toBe($aContent)
        ->and($b->content?->content)->toBe($bContent);
});

test('section reorder matches sections get order', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($page->draftVersion, $template, 0);
    $b = createPageSectionReorderRow($page->draftVersion, $template, 1);
    $c = createPageSectionReorderRow($page->draftVersion, $template, 2);

    loginPageSectionReorderUser($user);

    $response = statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [$c->public_id, $a->public_id, $b->public_id],
    ])->assertOk();

    statefulGetPageSectionReorder(pageSectionReorderListUri($account, $website, $page))
        ->assertOk()
        ->assertJsonPath('data.0.id', $response->json('data.0.id'))
        ->assertJsonPath('data.1.id', $response->json('data.1.id'))
        ->assertJsonPath('data.2.id', $response->json('data.2.id'));
});

test('add section after reorder appends next sort order', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($page->draftVersion, $template, 0);
    $b = createPageSectionReorderRow($page->draftVersion, $template, 1);
    $c = createPageSectionReorderRow($page->draftVersion, $template, 2);

    loginPageSectionReorderUser($user);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [$a->public_id, $b->public_id, $c->public_id],
    ])->assertOk();

    statefulPostPageSectionReorder(pageSectionReorderAddUri($account, $website, $page), [
        'section_template_id' => $template->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.sort_order', 3);
});

test('section reorder rolls back clone when persistence fails', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($versionOne, $template, 0);
    $b = createPageSectionReorderRow($versionOne, $template, 1);

    simulatePageSectionReorderPublished($page, $user);

    loginPageSectionReorderUser($user);

    PageSection::updating(function (): void {
        throw new RuntimeException('Simulated reorder persistence failure');
    });

    try {
        statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
            'section_ids' => [$b->public_id, $a->public_id],
        ])->assertStatus(500);
    } finally {
        PageSection::flushEventListeners();
    }

    $page->refresh();

    expect(PageVersion::query()->count())->toBe(1)
        ->and($a->refresh()->sort_order)->toBe(0)
        ->and($b->refresh()->sort_order)->toBe(1);
});

test('ahead draft reorder does not create another version', function () {
    $user = createPageSectionReorderUser();
    $account = attachPageSectionReorderMembership($user);
    $website = createPageSectionReorderWebsite($account);
    $page = createPageSectionReorderPage($website, $user);
    $versionOne = $page->draftVersion;
    $template = createPageSectionReorderTemplate();

    $a = createPageSectionReorderRow($versionOne, $template, 0);
    $b = createPageSectionReorderRow($versionOne, $template, 1);

    simulatePageSectionReorderPublished($page, $user);

    $versionTwo = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $versionOne, $user);
    $page->assignDraftVersion($versionTwo);

    loginPageSectionReorderUser($user);

    statefulPutPageSectionReorder(pageSectionReorderUri($account, $website, $page), [
        'section_ids' => [$b->public_id, $a->public_id],
    ])->assertOk();

    expect(PageVersion::query()->count())->toBe(2);
});
