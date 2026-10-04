<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\User;
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

function accountDiscoveryOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncAccountDiscoveryCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetJsonForAccounts(string $uri): TestResponse
{
    $response = test()->withHeaders(accountDiscoveryOriginHeaders())->getJson($uri);

    syncAccountDiscoveryCookies($response);

    return $response;
}

function statefulPostJsonForAccounts(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(accountDiscoveryOriginHeaders())->postJson($uri, $data);

    syncAccountDiscoveryCookies($response);

    return $response;
}

function loginAccountDiscoveryUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostJsonForAccounts('/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

function createAccountDiscoveryUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function attachAccessibleAccount(
    User $user,
    string $accountName,
    string $roleName = 'owner',
    string $accountStatus = 'active',
    string $membershipStatus = 'active',
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
        'name' => $roleName,
        'guard_name' => AccountPermissionSeeder::GUARD,
        'team_id' => $account->id,
    ]);

    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => User::class,
        'model_id' => $user->id,
        'team_id' => $account->id,
    ]);

    return $account;
}

function attachAccessibleAccountWithoutRole(User $user, string $accountName): Account
{
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

    return $account;
}

function attachAdditionalAccountRole(User $user, Account $account, string $roleName): void
{
    $role = Role::query()->create([
        'name' => $roleName,
        'guard_name' => AccountPermissionSeeder::GUARD,
        'team_id' => $account->id,
    ]);

    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => User::class,
        'model_id' => $user->id,
        'team_id' => $account->id,
    ]);
}

test('unauthenticated account discovery request is unauthorized', function () {
    statefulGetJsonForAccounts('/accounts')
        ->assertUnauthorized();
});

test('authenticated user sees accessible active account with role', function () {
    $user = createAccountDiscoveryUser();
    $account = attachAccessibleAccount($user, 'Ada Account');

    loginAccountDiscoveryUser($user);

    statefulGetJsonForAccounts('/accounts')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                [
                    'id' => $account->id,
                    'name' => 'Ada Account',
                    'status' => 'active',
                    'role' => 'owner',
                ],
            ],
        ]);
});

test('accounts without membership are excluded from discovery', function () {
    $member = createAccountDiscoveryUser(['email' => 'member@example.com']);
    $outsider = createAccountDiscoveryUser(['email' => 'outsider@example.com']);

    attachAccessibleAccount($member, 'Member Account');

    Account::query()->create([
        'owner_id' => $outsider->id,
        'name' => 'No Membership Account',
        'status' => 'active',
    ]);

    loginAccountDiscoveryUser($outsider);

    statefulGetJsonForAccounts('/accounts')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('inactive membership excludes account from discovery', function () {
    $user = createAccountDiscoveryUser();
    attachAccessibleAccount($user, 'Inactive Membership', membershipStatus: 'inactive');

    loginAccountDiscoveryUser($user);

    statefulGetJsonForAccounts('/accounts')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('inactive account is excluded from discovery', function () {
    $user = createAccountDiscoveryUser();
    attachAccessibleAccount($user, 'Inactive Account', accountStatus: 'inactive');

    loginAccountDiscoveryUser($user);

    statefulGetJsonForAccounts('/accounts')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('soft deleted account is excluded from discovery', function () {
    $user = createAccountDiscoveryUser();
    $account = attachAccessibleAccount($user, 'Deleted Account');
    $account->delete();

    loginAccountDiscoveryUser($user);

    statefulGetJsonForAccounts('/accounts')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('owner id alone does not grant discovery access without active membership', function () {
    $owner = createAccountDiscoveryUser(['email' => 'owner@example.com']);

    Account::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Owner Without Membership',
        'status' => 'active',
    ]);

    loginAccountDiscoveryUser($owner);

    statefulGetJsonForAccounts('/accounts')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('multiple accessible accounts are returned in id order with correct roles', function () {
    $user = createAccountDiscoveryUser();

    $firstAccount = attachAccessibleAccount($user, 'First Account', 'owner');
    $secondAccount = attachAccessibleAccount($user, 'Second Account', 'member');

    loginAccountDiscoveryUser($user);

    statefulGetJsonForAccounts('/accounts')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                [
                    'id' => $firstAccount->id,
                    'name' => 'First Account',
                    'status' => 'active',
                    'role' => 'owner',
                ],
                [
                    'id' => $secondAccount->id,
                    'name' => 'Second Account',
                    'status' => 'active',
                    'role' => 'member',
                ],
            ],
        ]);
});

test('active membership without role returns null role metadata', function () {
    $user = createAccountDiscoveryUser();
    $account = attachAccessibleAccountWithoutRole($user, 'No Role Account');

    loginAccountDiscoveryUser($user);

    statefulGetJsonForAccounts('/accounts')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                [
                    'id' => $account->id,
                    'name' => 'No Role Account',
                    'status' => 'active',
                    'role' => null,
                ],
            ],
        ]);
});

test('multiple distinct roles for the same account return null role metadata', function () {
    $user = createAccountDiscoveryUser();
    $account = attachAccessibleAccount($user, 'Ambiguous Role Account', 'owner');
    attachAdditionalAccountRole($user, $account, 'member');

    loginAccountDiscoveryUser($user);

    statefulGetJsonForAccounts('/accounts')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                [
                    'id' => $account->id,
                    'name' => 'Ambiguous Role Account',
                    'status' => 'active',
                    'role' => null,
                ],
            ],
        ]);
});

test('multiple distinct roles for one account do not fail account discovery', function () {
    $user = createAccountDiscoveryUser();
    $ambiguousAccount = attachAccessibleAccount($user, 'Ambiguous Role Account', 'owner');
    attachAdditionalAccountRole($user, $ambiguousAccount, 'admin');
    $clearAccount = attachAccessibleAccount($user, 'Clear Role Account', 'member');

    loginAccountDiscoveryUser($user);

    $response = statefulGetJsonForAccounts('/accounts')->assertOk();

    expect($response->json('data'))->toHaveCount(2);

    $rolesByName = collect($response->json('data'))
        ->mapWithKeys(fn (array $item) => [$item['name'] => $item['role']]);

    expect($rolesByName->get('Ambiguous Role Account'))->toBeNull()
        ->and($rolesByName->get('Clear Role Account'))->toBe('member');
});

test('account scoped roles do not leak between accounts', function () {
    $user = createAccountDiscoveryUser();

    attachAccessibleAccount($user, 'Owner Account', 'owner');
    attachAccessibleAccount($user, 'Member Account', 'member');

    loginAccountDiscoveryUser($user);

    $response = statefulGetJsonForAccounts('/accounts')->assertOk();

    $rolesByAccountName = collect($response->json('data'))
        ->mapWithKeys(fn (array $item) => [$item['name'] => $item['role']]);

    expect($rolesByAccountName->get('Owner Account'))->toBe('owner')
        ->and($rolesByAccountName->get('Member Account'))->toBe('member');
});

test('account discovery response exposes only intended fields', function () {
    $user = createAccountDiscoveryUser();
    attachAccessibleAccount($user, 'Ada Account');

    loginAccountDiscoveryUser($user);

    $response = statefulGetJsonForAccounts('/accounts')->assertOk();

    expect(array_keys($response->json()))->toBe(['data']);

    foreach ($response->json('data') as $item) {
        expect(array_keys($item))->toEqual(['id', 'name', 'status', 'role']);
    }
});
