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
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function accountShowOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncAccountShowCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetJsonForAccountShow(string $uri): TestResponse
{
    $response = test()->withHeaders(accountShowOriginHeaders())->getJson($uri);

    syncAccountShowCookies($response);

    return $response;
}

function statefulPostJsonForAccountShow(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(accountShowOriginHeaders())->postJson($uri, $data);

    syncAccountShowCookies($response);

    return $response;
}

function createAccountShowUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginAccountShowUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostJsonForAccountShow('/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachMembershipWithRole(
    User $user,
    string $accountName,
    string $roleName = 'owner',
    array $permissions = AccountPermissionSeeder::PERMISSIONS,
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

    $role->givePermissionTo($permissions);

    DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => User::class,
        'model_id' => $user->id,
        'team_id' => $account->id,
    ]);

    return $account;
}

function expectedAccountShowPayload(Account $account): array
{
    return [
        'data' => [
            'id' => $account->id,
            'name' => $account->name,
            'status' => $account->status,
        ],
    ];
}

test('unauthenticated account show request is unauthorized', function () {
    $account = Account::query()->create([
        'owner_id' => createAccountShowUser()->id,
        'name' => 'Example',
        'status' => 'active',
    ]);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)
        ->assertUnauthorized();
});

test('active member with account view permission can show the account', function () {
    $user = createAccountShowUser();
    $account = attachMembershipWithRole($user, 'Ada Account');

    loginAccountShowUser($user);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)
        ->assertOk()
        ->assertExactJson(expectedAccountShowPayload($account));
});

test('account show response exposes only intended fields', function () {
    $user = createAccountShowUser();
    $account = attachMembershipWithRole($user, 'Ada Account');

    loginAccountShowUser($user);

    $response = statefulGetJsonForAccountShow('/accounts/'.$account->id)->assertOk();

    expect(array_keys($response->json('data')))->toEqual(['id', 'name', 'status']);
});

test('active member without account view permission is forbidden', function () {
    $user = createAccountShowUser();
    $account = attachMembershipWithRole($user, 'Limited Account', 'member', ['website.view']);

    loginAccountShowUser($user);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)
        ->assertForbidden();
});

test('another users account without membership is not found', function () {
    $member = createAccountShowUser(['email' => 'member@example.com']);
    $outsider = createAccountShowUser(['email' => 'outsider@example.com']);

    $account = attachMembershipWithRole($member, 'Member Account');

    loginAccountShowUser($outsider);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)
        ->assertNotFound();
});

test('inactive membership is forbidden', function () {
    $user = createAccountShowUser();
    $account = attachMembershipWithRole($user, 'Inactive Member', membershipStatus: 'inactive');

    loginAccountShowUser($user);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)
        ->assertForbidden();
});

test('inactive account with membership is forbidden', function () {
    $user = createAccountShowUser();
    $account = attachMembershipWithRole($user, 'Inactive Account', accountStatus: 'inactive');

    loginAccountShowUser($user);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)
        ->assertForbidden();
});

test('soft deleted account is not found', function () {
    $user = createAccountShowUser();
    $account = attachMembershipWithRole($user, 'Deleted Account');
    $account->delete();

    loginAccountShowUser($user);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)
        ->assertNotFound();
});

test('nonexistent account is not found', function () {
    $user = createAccountShowUser();
    loginAccountShowUser($user);

    statefulGetJsonForAccountShow('/accounts/999999')
        ->assertNotFound();
});

test('owner id without membership does not grant account show access', function () {
    $owner = createAccountShowUser(['email' => 'owner@example.com']);

    $account = Account::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Owner Without Membership',
        'status' => 'active',
    ]);

    loginAccountShowUser($owner);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)
        ->assertNotFound();
});

test('account scoped permissions do not authorize another account', function () {
    $user = createAccountShowUser();

    attachMembershipWithRole($user, 'Account A', 'owner');
    $accountB = attachMembershipWithRole($user, 'Account B', 'member', ['website.view']);

    loginAccountShowUser($user);

    statefulGetJsonForAccountShow('/accounts/'.$accountB->id)
        ->assertForbidden();
});

test('spatie team context is restored after successful account show', function () {
    $user = createAccountShowUser();
    $account = attachMembershipWithRole($user, 'Ada Account');

    loginAccountShowUser($user);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(999);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)->assertOk();

    expect($registrar->getPermissionsTeamId())->toBe(999);
});

test('spatie team context is restored after policy denial', function () {
    $user = createAccountShowUser();
    $account = attachMembershipWithRole($user, 'Limited Account', 'member', ['website.view']);

    loginAccountShowUser($user);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(999);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)->assertForbidden();

    expect($registrar->getPermissionsTeamId())->toBe(999);
});

test('tenant gate denial does not change spatie team context', function () {
    $user = createAccountShowUser();
    $account = attachMembershipWithRole($user, 'Inactive Member', membershipStatus: 'inactive');

    loginAccountShowUser($user);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(999);

    statefulGetJsonForAccountShow('/accounts/'.$account->id)->assertForbidden();

    expect($registrar->getPermissionsTeamId())->toBe(999);
});

test('registration restores spatie team context after account bootstrap', function () {
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(4242);

    statefulPostJsonForAccountShow('/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Str0ngPass!',
        'password_confirmation' => 'Str0ngPass!',
    ])->assertCreated();

    expect($registrar->getPermissionsTeamId())->toBe(4242);
});
