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

function pageSectionReadOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageSectionReadCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetPageSections(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionReadOriginHeaders())->getJson($uri);

    syncPageSectionReadCookies($response);

    return $response;
}

function statefulPostPageSectionRead(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageSectionReadOriginHeaders())->postJson($uri, $data);

    syncPageSectionReadCookies($response);

    return $response;
}

function createPageSectionReadUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada-sections-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginPageSectionReadUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostPageSectionRead('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachPageSectionReadMembership(
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

function createPageSectionReadWebsite(Account $account): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => 'example-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function pageSectionsUri(Account $account, Website $website, Page $page): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections';
}

function createPageSectionReadPage(Website $website, User $creator): Page
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
function createPageSectionReadTemplateFixture(array $typeOverrides = [], array $templateOverrides = []): array
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

test('unauthenticated page sections read is unauthorized', function () {
    $user = createPageSectionReadUser();
    $account = attachPageSectionReadMembership($user, 'Ada Account');
    $website = createPageSectionReadWebsite($account);
    $page = createPageSectionReadPage($website, $user);

    test()->getJson(pageSectionsUri($account, $website, $page))->assertUnauthorized();
});

test('page view permission is required to list page sections', function () {
    $user = createPageSectionReadUser();
    $account = attachPageSectionReadMembership($user, 'Ada Account', ['page.update']);
    $website = createPageSectionReadWebsite($account);
    $page = createPageSectionReadPage($website, $user);

    loginPageSectionReadUser($user);

    statefulGetPageSections(pageSectionsUri($account, $website, $page))
        ->assertForbidden();
});

test('page sections read returns draft sections with stable public ids', function () {
    $user = createPageSectionReadUser();
    $account = attachPageSectionReadMembership($user, 'Ada Account', ['page.view']);
    $website = createPageSectionReadWebsite($account);
    $page = createPageSectionReadPage($website, $user);
    $version = $page->draftVersion;

    $fixture = createPageSectionReadTemplateFixture([
        'key' => 'hero',
    ], [
        'key' => 'hero-centered',
        'name' => 'Centered Hero',
    ]);

    $second = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 2,
        'is_visible' => true,
        'settings' => null,
    ]);

    $first = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 1,
        'is_visible' => false,
        'settings' => ['theme' => 'dark'],
    ]);

    PageSectionContent::query()->create([
        'page_section_id' => $first->id,
        'content' => ['headline' => 'Hello'],
    ]);

    loginPageSectionReadUser($user);

    $response = statefulGetPageSections(pageSectionsUri($account, $website, $page))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->public_id)
        ->assertJsonPath('data.0.sort_order', 1)
        ->assertJsonPath('data.0.is_visible', false)
        ->assertJsonPath('data.0.settings', ['theme' => 'dark'])
        ->assertJsonPath('data.0.content', ['headline' => 'Hello'])
        ->assertJsonPath('data.0.template.id', $fixture['template']->id)
        ->assertJsonPath('data.0.template.key', 'hero-centered')
        ->assertJsonPath('data.0.template.name', 'Centered Hero')
        ->assertJsonPath('data.0.template.type_key', 'hero')
        ->assertJsonPath('data.1.id', $second->public_id);

    $decoded = json_decode($response->getContent(), false);

    expect($decoded->data[1]->settings)->toBeInstanceOf(stdClass::class)
        ->and($decoded->data[1]->content)->toBeInstanceOf(stdClass::class);

    $payload = $response->json('data');

    expect(collect($payload)->pluck('id'))->not->toContain($first->id)
        ->and(collect($payload)->pluck('id'))->not->toContain($second->id);
});

test('page sections read returns ahead draft sections only', function () {
    $user = createPageSectionReadUser();
    $account = attachPageSectionReadMembership($user, 'Ada Account');
    $website = createPageSectionReadWebsite($account);
    $page = createPageSectionReadPage($website, $user);

    $published = $page->draftVersion;
    $fixture = createPageSectionReadTemplateFixture();

    PageSection::query()->create([
        'page_version_id' => $published->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 0,
    ]);

    $page->update(['published_version_id' => $published->id]);

    $draft = app(PageVersionSnapshotCloner::class)->cloneToNewDraftVersion($page, $published, $user);
    $page->assignDraftVersion($draft);

    $draftSection = PageSection::query()
        ->where('page_version_id', $draft->id)
        ->firstOrFail();

    $draftSection->update(['settings' => ['marker' => 'draft-only']]);

    loginPageSectionReadUser($user);

    statefulGetPageSections(pageSectionsUri($account, $website, $page))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.settings.marker', 'draft-only');

    expect(PageSection::query()->where('page_version_id', $published->id)->first()?->settings)->toBeNull();
});

test('page sections read does not clone when draft equals published', function () {
    $user = createPageSectionReadUser();
    $account = attachPageSectionReadMembership($user, 'Ada Account');
    $website = createPageSectionReadWebsite($account);
    $page = createPageSectionReadPage($website, $user);
    $version = $page->draftVersion;

    $fixture = createPageSectionReadTemplateFixture();
    $section = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 0,
    ]);

    $page->update(['published_version_id' => $version->id]);

    loginPageSectionReadUser($user);

    statefulGetPageSections(pageSectionsUri($account, $website, $page))
        ->assertOk()
        ->assertJsonPath('data.0.id', $section->public_id);

    expect(PageVersion::query()->count())->toBe(1);
});

test('page sections read excludes soft deleted sections', function () {
    $user = createPageSectionReadUser();
    $account = attachPageSectionReadMembership($user, 'Ada Account');
    $website = createPageSectionReadWebsite($account);
    $page = createPageSectionReadPage($website, $user);
    $version = $page->draftVersion;
    $fixture = createPageSectionReadTemplateFixture();

    $visible = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 0,
    ]);

    $deleted = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 1,
    ]);

    $deleted->delete();

    loginPageSectionReadUser($user);

    statefulGetPageSections(pageSectionsUri($account, $website, $page))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->public_id);
});

test('page sections read still returns sections when template is inactive', function () {
    $user = createPageSectionReadUser();
    $account = attachPageSectionReadMembership($user, 'Ada Account');
    $website = createPageSectionReadWebsite($account);
    $page = createPageSectionReadPage($website, $user);
    $version = $page->draftVersion;

    $fixture = createPageSectionReadTemplateFixture([], [
        'key' => 'legacy-template',
        'name' => 'Legacy',
    ]);

    $section = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $fixture['template']->id,
        'sort_order' => 0,
    ]);

    $fixture['template']->update(['status' => 'inactive']);
    $fixture['type']->update(['status' => 'inactive']);

    loginPageSectionReadUser($user);

    statefulGetPageSections(pageSectionsUri($account, $website, $page))
        ->assertOk()
        ->assertJsonPath('data.0.id', $section->public_id)
        ->assertJsonPath('data.0.template.key', 'legacy-template');
});

test('page sections read returns not found for wrong website or account', function () {
    $user = createPageSectionReadUser();
    $account = attachPageSectionReadMembership($user, 'Ada Account');
    $otherUser = createPageSectionReadUser();
    $otherAccount = attachPageSectionReadMembership($otherUser, 'Other Account');
    $website = createPageSectionReadWebsite($account);
    $otherWebsite = createPageSectionReadWebsite($otherAccount);
    $page = createPageSectionReadPage($website, $user);

    loginPageSectionReadUser($user);

    statefulGetPageSections('/v1/accounts/'.$otherAccount->id.'/websites/'.$website->id.'/pages/'.$page->id)
        ->assertNotFound();

    statefulGetPageSections('/v1/accounts/'.$account->id.'/websites/'.$otherWebsite->id.'/pages/'.$page->id)
        ->assertNotFound();
});

test('page sections read returns not found for soft deleted page or website', function () {
    $user = createPageSectionReadUser();
    $account = attachPageSectionReadMembership($user, 'Ada Account');
    $website = createPageSectionReadWebsite($account);
    $page = createPageSectionReadPage($website, $user);

    loginPageSectionReadUser($user);

    $page->delete();

    statefulGetPageSections(pageSectionsUri($account, $website, $page))
        ->assertNotFound();

    $page = createPageSectionReadPage($website, $user);
    $website->delete();

    statefulGetPageSections(pageSectionsUri($account, $website, $page))
        ->assertNotFound();
});
