<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteSetting;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function websiteManagementOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncWebsiteManagementCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetJsonForWebsites(string $uri): TestResponse
{
    $response = test()->withHeaders(websiteManagementOriginHeaders())->getJson($uri);

    syncWebsiteManagementCookies($response);

    return $response;
}

function statefulPostJsonForWebsites(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(websiteManagementOriginHeaders())->postJson($uri, $data);

    syncWebsiteManagementCookies($response);

    return $response;
}

function createWebsiteManagementUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginWebsiteManagementUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostJsonForWebsites('/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachWebsiteMembership(
    User $user,
    string $accountName,
    array $permissions = AccountPermissionSeeder::PERMISSIONS,
): Account {
    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => $accountName,
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

function websiteResourcePayload(Website $website): array
{
    return [
        'id' => $website->id,
        'name' => $website->name,
        'subdomain' => $website->subdomain,
        'status' => $website->status,
    ];
}

function accountWebsitesUri(Account $account, ?Website $website = null): string
{
    $uri = '/accounts/'.$account->id.'/websites';

    if ($website !== null) {
        $uri .= '/'.$website->id;
    }

    return $uri;
}

test('unauthenticated website list create and show requests are unauthorized', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');
    $website = Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example',
        'subdomain' => 'example',
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    statefulGetJsonForWebsites(accountWebsitesUri($account))->assertUnauthorized();
    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'New'])->assertUnauthorized();
    statefulGetJsonForWebsites(accountWebsitesUri($account, $website))->assertUnauthorized();
});

test('active member with website view can list and show websites', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');
    $website = Website::query()->create([
        'account_id' => $account->id,
        'name' => 'My Business',
        'subdomain' => 'my-business',
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    loginWebsiteManagementUser($user);

    statefulGetJsonForWebsites(accountWebsitesUri($account))
        ->assertOk()
        ->assertExactJson(['data' => [websiteResourcePayload($website)]]);

    statefulGetJsonForWebsites(accountWebsitesUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => websiteResourcePayload($website)]);
});

test('missing website view permission forbids list and show', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account', ['website.create']);

    loginWebsiteManagementUser($user);

    statefulGetJsonForWebsites(accountWebsitesUri($account))->assertForbidden();
});

test('website create permission allows website creation', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
        ->assertCreated();
});

test('missing website create permission forbids website creation', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account', ['account.view', 'website.view']);

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
        ->assertForbidden();
});

test('website creation returns controlled resource with server defaults', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    $response = statefulPostJsonForWebsites(accountWebsitesUri($account), [
        'name' => 'My Business',
    ])->assertCreated();

    $website = Website::query()->firstOrFail();

    $response->assertExactJson(['data' => websiteResourcePayload($website)]);

    expect($website->account_id)->toBe($account->id)
        ->and($website->status)->toBe('draft')
        ->and($website->timezone)->toBe('UTC')
        ->and($website->published_at)->toBeNull()
        ->and($website->subdomain)->toBe('my-business');
});

test('website creation creates baseline settings transactionally', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
        ->assertCreated();

    $website = Website::query()->firstOrFail();

    expect(WebsiteSetting::query()->count())->toBe(1);

    $settings = WebsiteSetting::query()->where('website_id', $website->id)->first();

    expect($settings)->not->toBeNull()
        ->and($settings->site_name)->toBe('My Business');
});

test('website creation rolls back when baseline settings cannot be created', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    WebsiteSetting::creating(function (): void {
        throw new RuntimeException('Simulated settings failure');
    });

    try {
        statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
            ->assertStatus(500);
    } finally {
        WebsiteSetting::flushEventListeners();
    }

    expect(Website::query()->count())->toBe(0)
        ->and(WebsiteSetting::query()->count())->toBe(0);
});

test('website creation rejects client controlled fields', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($account), [
        'name' => 'My Business',
        'account_id' => 999,
        'subdomain' => 'hijacked',
        'status' => 'published',
        'timezone' => 'America/New_York',
        'published_at' => now()->toISOString(),
        'id' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'account_id',
            'subdomain',
            'status',
            'timezone',
            'published_at',
            'id',
        ]);
});

test('website creation rejects unknown fields', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($account), [
        'name' => 'My Business',
        'unexpected' => 'value',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unexpected']);
});

test('website subdomain is generated from the website name', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
        ->assertCreated()
        ->assertJsonPath('data.subdomain', 'my-business');
});

test('duplicate website names receive deterministic unique subdomains', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
        ->assertCreated()
        ->assertJsonPath('data.subdomain', 'my-business');

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
        ->assertCreated()
        ->assertJsonPath('data.subdomain', 'my-business-2');

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
        ->assertCreated()
        ->assertJsonPath('data.subdomain', 'my-business-3');
});

test('unusable website names still receive a safe generated subdomain', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => '!!!'])
        ->assertCreated()
        ->assertJsonPath('data.subdomain', 'site');
});

test('website list is ordered by id ascending', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    $first = Website::query()->create([
        'account_id' => $account->id,
        'name' => 'First',
        'subdomain' => 'first',
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    $second = Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Second',
        'subdomain' => 'second',
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    loginWebsiteManagementUser($user);

    $response = statefulGetJsonForWebsites(accountWebsitesUri($account))->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('website list excludes soft deleted websites', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    $active = Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Active',
        'subdomain' => 'active',
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    $deleted = Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Deleted',
        'subdomain' => 'deleted',
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
    $deleted->delete();

    loginWebsiteManagementUser($user);

    statefulGetJsonForWebsites(accountWebsitesUri($account))
        ->assertOk()
        ->assertExactJson(['data' => [websiteResourcePayload($active)]]);
});

test('website show returns not found for website belonging to another account', function () {
    $user = createWebsiteManagementUser();
    $accountA = attachWebsiteMembership($user, 'Account A');
    $accountB = attachWebsiteMembership($user, 'Account B');

    $websiteOnB = Website::query()->create([
        'account_id' => $accountB->id,
        'name' => 'On B',
        'subdomain' => 'on-b',
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    loginWebsiteManagementUser($user);

    statefulGetJsonForWebsites('/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id)
        ->assertNotFound();
});

test('account scoped permissions do not authorize website operations in another account', function () {
    $user = createWebsiteManagementUser();

    attachWebsiteMembership($user, 'Account A');
    $accountB = attachWebsiteMembership($user, 'Account B', ['account.view', 'website.view']);

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($accountB), ['name' => 'Blocked'])
        ->assertForbidden();
});

test('spatie team context is restored after website routes', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    loginWebsiteManagementUser($user);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(999);

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
        ->assertCreated();

    expect($registrar->getPermissionsTeamId())->toBe(999);
});

test('website creation retries subdomain allocation after unique collision', function () {
    $user = createWebsiteManagementUser();
    $account = attachWebsiteMembership($user, 'Ada Account');

    Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Existing',
        'subdomain' => 'my-business',
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    loginWebsiteManagementUser($user);

    statefulPostJsonForWebsites(accountWebsitesUri($account), ['name' => 'My Business'])
        ->assertCreated()
        ->assertJsonPath('data.subdomain', 'my-business-2');
});
