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

function websiteSettingsOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncWebsiteSettingsCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetJsonForWebsiteSettings(string $uri): TestResponse
{
    $response = test()->withHeaders(websiteSettingsOriginHeaders())->getJson($uri);

    syncWebsiteSettingsCookies($response);

    return $response;
}

function statefulPatchJsonForWebsiteSettings(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(websiteSettingsOriginHeaders())->patchJson($uri, $data);

    syncWebsiteSettingsCookies($response);

    return $response;
}

function createWebsiteSettingsUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada-settings@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginWebsiteSettingsUser(User $user, string $password = 'Str0ngPass!'): void
{
    test()->withHeaders(websiteSettingsOriginHeaders())->postJson('/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();

    Auth::forgetGuards();
}

/**
 * @param  list<string>  $permissions
 */
function attachWebsiteSettingsMembership(
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

function accountWebsiteSettingsUri(Account $account, Website $website): string
{
    return '/accounts/'.$account->id.'/websites/'.$website->id.'/settings';
}

/**
 * @return array{0: Website, 1: WebsiteSetting}
 */
function createWebsiteWithSettingsForTest(
    Account $account,
    array $websiteAttributes = [],
    array $settingsAttributes = [],
): array {
    $website = Website::query()->create(array_merge([
        'account_id' => $account->id,
        'name' => 'Dashboard Website Name',
        'subdomain' => 'site-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ], $websiteAttributes));

    $settings = WebsiteSetting::query()->create(array_merge([
        'website_id' => $website->id,
        'site_name' => 'My Business',
    ], $settingsAttributes));

    return [$website, $settings];
}

/**
 * @param  array<string, mixed>|null  $address
 * @param  array<string, mixed>|null  $socialLinks
 * @return array<string, mixed>
 */
function expectedWebsiteSettingsPayload(
    string $siteName = 'My Business',
    ?string $tagline = null,
    ?string $contactEmail = null,
    ?string $contactPhone = null,
    ?array $address = null,
    ?array $socialLinks = null,
): array {
    return [
        'site_name' => $siteName,
        'tagline' => $tagline,
        'contact_email' => $contactEmail,
        'contact_phone' => $contactPhone,
        'address' => $address,
        'social_links' => $socialLinks,
    ];
}

test('unauthenticated website settings get and patch are unauthorized', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    statefulGetJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website))->assertUnauthorized();
    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), ['tagline' => 'Hi'])
        ->assertUnauthorized();
});

test('website settings return not found for website outside route account', function () {
    $user = createWebsiteSettingsUser();
    $accountA = attachWebsiteSettingsMembership($user, 'Account A');
    $accountB = attachWebsiteSettingsMembership($user, 'Account B');
    [$websiteOnB] = createWebsiteWithSettingsForTest($accountB);

    loginWebsiteSettingsUser($user);

    statefulGetJsonForWebsiteSettings(accountWebsiteSettingsUri($accountA, $websiteOnB))->assertNotFound();
    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($accountA, $websiteOnB), ['tagline' => 'Nope'])
        ->assertNotFound();
});

test('member with website view can get website settings', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulGetJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload()]);
});

test('missing website view permission forbids settings get', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account', ['website.update']);
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulGetJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website))->assertForbidden();
});

test('member with website update can patch website settings', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), ['tagline' => 'Hello'])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload(tagline: 'Hello')]);
});

test('view only member cannot patch website settings', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account', ['account.view', 'website.view']);
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), ['tagline' => 'Blocked'])
        ->assertForbidden();
});

test('website settings get exposes only the allowed contract fields', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website, $settings] = createWebsiteWithSettingsForTest($account, [], [
        'tagline' => 'Tag',
        'contact_email' => 'hello@example.com',
        'contact_phone' => '+1',
        'address' => ['city' => 'Chennai'],
        'social_links' => ['facebook' => 'https://facebook.com/acme'],
    ]);

    loginWebsiteSettingsUser($user);

    $response = statefulGetJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website))->assertOk();

    expect(array_keys($response->json('data')))->toBe([
        'site_name',
        'tagline',
        'contact_email',
        'contact_phone',
        'address',
        'social_links',
    ])->and($response->json('data'))->not->toHaveKeys(['id', 'website_id', 'created_at', 'updated_at'])
        ->and($settings->id)->toBeInt();
});

test('partial scalar patch preserves omitted fields', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account, [], [
        'tagline' => 'Keep me',
        'contact_email' => 'keep@example.com',
    ]);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'contact_phone' => '+44',
    ])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload(
            tagline: 'Keep me',
            contactEmail: 'keep@example.com',
            contactPhone: '+44',
        )]);
});

test('nullable scalar fields can be cleared with null', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account, [], [
        'tagline' => 'Remove',
        'contact_email' => 'remove@example.com',
        'contact_phone' => '123',
    ]);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'tagline' => null,
        'contact_email' => null,
        'contact_phone' => null,
    ])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload()]);
});

test('site name null is rejected on patch', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), ['site_name' => null])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['site_name']);
});

test('patching site name does not change websites name', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'site_name' => 'Public Facing Name',
    ])->assertOk();

    expect($website->fresh()->name)->toBe('Dashboard Website Name');
});

test('unknown top level fields are rejected on patch', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'timezone' => 'UTC',
        'extra' => 'nope',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['timezone', 'extra']);
});

test('internal server fields are rejected on patch', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'website_id' => 999,
        'id' => 1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['website_id', 'id']);
});

test('empty patch body is a no op', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account, [], ['tagline' => 'Stable']);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload(tagline: 'Stable')]);
});

test('address patch merges and preserves omitted nested keys', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account, [], [
        'address' => [
            'line1' => '10 Main Street',
            'city' => 'Chennai',
        ],
    ]);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'address' => ['city' => 'Hyderabad'],
    ])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload(
            address: [
                'line1' => '10 Main Street',
                'city' => 'Hyderabad',
            ],
        )]);
});

test('address nested null clears only the supplied component', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account, [], [
        'address' => [
            'line1' => '10 Main Street',
            'city' => 'Chennai',
        ],
    ]);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'address' => ['city' => null],
    ])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload(
            address: [
                'line1' => '10 Main Street',
                'city' => null,
            ],
        )]);
});

test('address null clears the entire address column', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account, [], [
        'address' => ['city' => 'Chennai'],
    ]);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), ['address' => null])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload()]);
});

test('unknown address keys are rejected', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'address' => ['planet' => 'Mars'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address.planet']);
});

test('invalid address structures are rejected', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'address' => ['line1' => ['nested']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['address.line1']);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'address' => ['not-a-list-item'],
    ])->assertUnprocessable();
});

test('address values enforce max length', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'address' => ['line1' => str_repeat('a', 256)],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address.line1']);
});

test('social links patch merges and preserves omitted keys', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account, [], [
        'social_links' => [
            'facebook' => 'https://facebook.com/acme',
            'instagram' => 'https://instagram.com/acme',
        ],
    ]);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'social_links' => ['x' => 'https://x.com/acme'],
    ])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload(
            socialLinks: [
                'facebook' => 'https://facebook.com/acme',
                'instagram' => 'https://instagram.com/acme',
                'x' => 'https://x.com/acme',
            ],
        )]);
});

test('social nested null clears only the supplied link', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account, [], [
        'social_links' => [
            'facebook' => 'https://facebook.com/acme',
            'instagram' => 'https://instagram.com/acme',
        ],
    ]);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'social_links' => ['instagram' => null],
    ])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload(
            socialLinks: [
                'facebook' => 'https://facebook.com/acme',
                'instagram' => null,
            ],
        )]);
});

test('social links null clears the entire column', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account, [], [
        'social_links' => ['facebook' => 'https://facebook.com/acme'],
    ]);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), ['social_links' => null])
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload()]);
});

test('unknown social platform keys are rejected', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'social_links' => ['tiktok' => 'https://tiktok.com/acme'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['social_links.tiktok']);
});

test('malformed social urls are rejected', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'social_links' => ['facebook' => 'not-a-url'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['social_links.facebook']);
});

test('non http or https social urls are rejected', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'social_links' => ['facebook' => 'ftp://facebook.com/acme'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['social_links.facebook']);
});

test('http and https social urls are accepted', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), [
        'social_links' => [
            'facebook' => 'http://facebook.com/acme',
            'youtube' => 'https://youtube.com/acme',
        ],
    ])->assertOk();
});

test('website settings resource strips unsupported legacy nested keys without mutating storage', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website, $settings] = createWebsiteWithSettingsForTest($account, [], [
        'address' => [
            'line1' => '10 Main Street',
            'legacy_key' => 'hidden',
        ],
        'social_links' => [
            'facebook' => 'https://facebook.com/acme',
            'legacy_platform' => 'https://example.com',
        ],
    ]);

    loginWebsiteSettingsUser($user);

    statefulGetJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => expectedWebsiteSettingsPayload(
            address: ['line1' => '10 Main Street'],
            socialLinks: ['facebook' => 'https://facebook.com/acme'],
        )]);

    $settings->refresh();

    expect($settings->address)->toHaveKey('legacy_key')
        ->and($settings->social_links)->toHaveKey('legacy_platform');
});

test('missing website settings row returns not found for get and patch', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');

    $website = Website::query()->create([
        'account_id' => $account->id,
        'name' => 'No Settings',
        'subdomain' => 'no-settings',
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    loginWebsiteSettingsUser($user);

    statefulGetJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website))->assertNotFound();
    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website), ['tagline' => 'Nope'])
        ->assertNotFound();
});

test('account scoped permissions do not authorize settings in another account', function () {
    $user = createWebsiteSettingsUser();

    attachWebsiteSettingsMembership($user, 'Account A');
    $accountB = attachWebsiteSettingsMembership($user, 'Account B', ['account.view', 'website.view']);
    [$websiteOnB] = createWebsiteWithSettingsForTest($accountB);

    loginWebsiteSettingsUser($user);

    statefulPatchJsonForWebsiteSettings(accountWebsiteSettingsUri($accountB, $websiteOnB), ['tagline' => 'Blocked'])
        ->assertForbidden();
});

test('spatie team context is restored after website settings routes', function () {
    $user = createWebsiteSettingsUser();
    $account = attachWebsiteSettingsMembership($user, 'Ada Account');
    [$website] = createWebsiteWithSettingsForTest($account);

    loginWebsiteSettingsUser($user);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(999);

    statefulGetJsonForWebsiteSettings(accountWebsiteSettingsUri($account, $website))->assertOk();

    expect($registrar->getPermissionsTeamId())->toBe(999);
});
