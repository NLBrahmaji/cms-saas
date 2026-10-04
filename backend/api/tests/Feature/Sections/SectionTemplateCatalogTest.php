<?php

use App\Models\SectionTemplate;
use App\Models\SectionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withCredentials();
});

function sectionCatalogOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncSectionCatalogCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetSectionCatalog(string $uri): TestResponse
{
    $response = test()->withHeaders(sectionCatalogOriginHeaders())->getJson($uri);

    syncSectionCatalogCookies($response);

    return $response;
}

function loginSectionCatalogUser(User $user, string $password = 'Str0ngPass!'): void
{
    test()->withHeaders(sectionCatalogOriginHeaders())->postJson('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

function createSectionCatalogUser(): User
{
    return User::query()->create([
        'name' => 'Catalog User',
        'email' => 'catalog-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

test('section template catalog requires authentication', function () {
    test()->getJson('/v1/section-templates')->assertUnauthorized();
});

test('section template catalog returns active types and templates in order', function () {
    $user = createSectionCatalogUser();
    loginSectionCatalogUser($user);

    $typeB = SectionType::query()->create([
        'name' => 'Type B',
        'key' => 'type-b',
        'content_schema' => ['type' => 'object'],
        'sort_order' => 2,
        'status' => SectionType::STATUS_ACTIVE,
    ]);

    $typeA = SectionType::query()->create([
        'name' => 'Type A',
        'key' => 'type-a',
        'description' => 'First type',
        'content_schema' => ['type' => 'object'],
        'sort_order' => 1,
        'status' => SectionType::STATUS_ACTIVE,
    ]);

    SectionType::query()->create([
        'name' => 'Inactive Type',
        'key' => 'inactive-type',
        'content_schema' => ['type' => 'object'],
        'status' => 'inactive',
    ]);

    SectionTemplate::query()->create([
        'section_type_id' => $typeA->id,
        'name' => 'Template Second',
        'key' => 'template-second',
        'sort_order' => 2,
        'status' => SectionTemplate::STATUS_ACTIVE,
    ]);

    SectionTemplate::query()->create([
        'section_type_id' => $typeA->id,
        'name' => 'Template First',
        'key' => 'template-first',
        'sort_order' => 1,
        'status' => SectionTemplate::STATUS_ACTIVE,
    ]);

    SectionTemplate::query()->create([
        'section_type_id' => $typeA->id,
        'name' => 'Inactive Template',
        'key' => 'inactive-template',
        'status' => 'inactive',
    ]);

    SectionTemplate::query()->create([
        'section_type_id' => $typeB->id,
        'name' => 'Type B Template',
        'key' => 'type-b-template',
        'status' => SectionTemplate::STATUS_ACTIVE,
    ]);

    statefulGetSectionCatalog('/v1/section-templates')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.key', 'type-a')
        ->assertJsonPath('data.0.name', 'Type A')
        ->assertJsonPath('data.0.description', 'First type')
        ->assertJsonPath('data.0.templates.0.key', 'template-first')
        ->assertJsonPath('data.0.templates.1.key', 'template-second')
        ->assertJsonPath('data.1.key', 'type-b')
        ->assertJsonMissingPath('data.0.templates.0.content_schema')
        ->assertJsonMissingPath('data.0.templates.0.settings_schema')
        ->assertJsonMissingPath('data.0.templates.0.created_at');
});

test('section template catalog omits active types without active templates', function () {
    $user = createSectionCatalogUser();
    loginSectionCatalogUser($user);

    SectionType::query()->create([
        'name' => 'Empty Type',
        'key' => 'empty-type',
        'content_schema' => ['type' => 'object'],
        'status' => SectionType::STATUS_ACTIVE,
    ]);

    statefulGetSectionCatalog('/v1/section-templates')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
