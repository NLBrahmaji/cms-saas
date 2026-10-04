<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Page;
use App\Models\PageSection;
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

function pageSectionJsonOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncPageSectionJsonCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPostPageSectionJson(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(pageSectionJsonOriginHeaders())->postJson($uri, $data);

    syncPageSectionJsonCookies($response);

    return $response;
}

function statefulGetPageSectionJson(string $uri): TestResponse
{
    $response = test()->withHeaders(pageSectionJsonOriginHeaders())->getJson($uri);

    syncPageSectionJsonCookies($response);

    return $response;
}

function loginPageSectionJsonUser(User $user): void
{
    test()->withHeaders(pageSectionJsonOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function attachPageSectionJsonMembership(User $user): Account
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

    $role->givePermissionTo(AccountPermissionSeeder::PERMISSIONS);

    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => User::class,
        'model_id' => $user->id,
        'team_id' => $account->id,
    ]);

    return $account;
}

function pageSectionJsonFixture(): array
{
    $user = User::query()->create([
        'name' => 'Ada',
        'email' => 'ada-json-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);

    $account = attachPageSectionJsonMembership($user);

    $website = Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site',
        'subdomain' => 'site-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    $page = Page::query()->create(['website_id' => $website->id]);

    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'About',
        'slug' => 'about',
        'created_by' => $user->id,
    ]);

    $page->assignDraftVersion($version);

    $type = SectionType::query()->create([
        'name' => 'Hero',
        'key' => 'hero',
        'content_schema' => ['type' => 'object'],
        'status' => SectionType::STATUS_ACTIVE,
    ]);

    $template = SectionTemplate::query()->create([
        'section_type_id' => $type->id,
        'name' => 'Centered Hero',
        'key' => 'hero-centered',
        'status' => SectionTemplate::STATUS_ACTIVE,
    ]);

    loginPageSectionJsonUser($user);

    $uri = '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/pages/'.$page->id.'/sections';

    return compact('user', 'account', 'website', 'page', 'template', 'uri');
}

test('post and get encode empty settings and content as json objects', function () {
    $fixture = pageSectionJsonFixture();

    $postResponse = statefulPostPageSectionJson($fixture['uri'], [
        'section_template_id' => $fixture['template']->id,
    ])->assertCreated();

    $postPayload = json_decode($postResponse->getContent(), false);

    expect($postPayload->data->settings)->toBeInstanceOf(stdClass::class)
        ->and($postPayload->data->content)->toBeInstanceOf(stdClass::class)
        ->and($postResponse->getContent())->toContain('"settings":{}')
        ->and($postResponse->getContent())->toContain('"content":{}');

    $getResponse = statefulGetPageSectionJson($fixture['uri'])->assertOk();

    $getPayload = json_decode($getResponse->getContent(), false);

    expect($getPayload->data[0]->settings)->toBeInstanceOf(stdClass::class)
        ->and($getPayload->data[0]->content)->toBeInstanceOf(stdClass::class)
        ->and($getResponse->getContent())->toContain('"settings":{}')
        ->and($getResponse->getContent())->toContain('"content":{}');
});

test('non-empty associative settings and content encode as json objects', function () {
    $fixture = pageSectionJsonFixture();

    statefulPostPageSectionJson($fixture['uri'], [
        'section_template_id' => $fixture['template']->id,
    ])->assertCreated();

    $section = PageSection::query()->firstOrFail();
    $section->update(['settings' => ['theme' => 'dark']]);
    $section->content?->update(['content' => ['headline' => 'Hello']]);

    $getResponse = statefulGetPageSectionJson($fixture['uri'])->assertOk();

    $getPayload = json_decode($getResponse->getContent(), false);

    expect($getPayload->data[0]->settings)->toBeInstanceOf(stdClass::class)
        ->and($getPayload->data[0]->settings->theme)->toBe('dark')
        ->and($getPayload->data[0]->content)->toBeInstanceOf(stdClass::class)
        ->and($getPayload->data[0]->content->headline)->toBe('Hello')
        ->and($getResponse->getContent())->toContain('"settings":{"theme":"dark"}')
        ->and($getResponse->getContent())->toContain('"content":{"headline":"Hello"}');
});
