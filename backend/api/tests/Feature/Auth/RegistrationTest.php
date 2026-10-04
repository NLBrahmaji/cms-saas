<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\User;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
});

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Str0ngPass!',
        'password_confirmation' => 'Str0ngPass!',
    ], $overrides);
}

function registerFromStatefulOrigin(array $payload = []): TestResponse
{
    return test()->withHeaders([
        'Origin' => 'http://localhost:3001',
    ])->postJson('/auth/register', registrationPayload($payload));
}

test('successful registration returns 201 with expected response structure', function () {
    $response = registerFromStatefulOrigin();

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'account' => ['id', 'name', 'status'],
        ])
        ->assertJsonPath('user.name', 'Ada Lovelace')
        ->assertJsonPath('user.email', 'ada@example.com')
        ->assertJsonPath('account.name', 'Ada Lovelace')
        ->assertJsonPath('account.status', 'active');
});

test('registration creates user with hashed password', function () {
    registerFromStatefulOrigin()->assertCreated();

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('Str0ngPass!', $user->password))->toBeTrue();
});

test('registration creates account with correct owner name and active status', function () {
    registerFromStatefulOrigin()->assertCreated();

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $account = Account::query()->firstOrFail();

    expect($account->owner_id)->toBe($user->id)
        ->and($account->name)->toBe('Ada Lovelace')
        ->and($account->status)->toBe('active');
});

test('registration creates active membership with joined_at', function () {
    registerFromStatefulOrigin()->assertCreated();

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $account = Account::query()->firstOrFail();

    $membership = AccountMember::query()->where('account_id', $account->id)
        ->where('user_id', $user->id)
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe('active')
        ->and($membership->joined_at)->not->toBeNull();
});

test('registration assigns account scoped owner role with baseline permissions', function () {
    registerFromStatefulOrigin()->assertCreated();

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
    $account = Account::query()->firstOrFail();

    $ownerRole = Role::query()
        ->where('name', 'owner')
        ->where('team_id', $account->id)
        ->first();

    expect($ownerRole)->not->toBeNull()
        ->and($ownerRole->permissions->pluck('name')->sort()->values()->all())
        ->toEqual(collect(AccountPermissionSeeder::PERMISSIONS)->sort()->values()->all());

    $roleAssignment = DB::table('model_has_roles')
        ->where('model_id', $user->id)
        ->where('model_type', User::class)
        ->where('role_id', $ownerRole->id)
        ->first();

    expect($roleAssignment)->not->toBeNull()
        ->and((int) $roleAssignment->team_id)->toBe($account->id);

    app(PermissionRegistrar::class)->setPermissionsTeamId($account->id);

    expect($user->hasRole('owner'))->toBeTrue();

    foreach (AccountPermissionSeeder::PERMISSIONS as $permission) {
        expect($user->can($permission))->toBeTrue();
    }
});

test('registration establishes an authenticated session', function () {
    registerFromStatefulOrigin()->assertCreated();

    $this->assertAuthenticated('web');
    expect(auth('web')->user()?->email)->toBe('ada@example.com');
});

test('duplicate email does not create partial tenant data', function () {
    registerFromStatefulOrigin()->assertCreated();

    registerFromStatefulOrigin(['email' => 'ada@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(User::query()->count())->toBe(1)
        ->and(Account::query()->count())->toBe(1)
        ->and(AccountMember::query()->count())->toBe(1);
});

test('validation failures do not create partial tenant data', function () {
    registerFromStatefulOrigin(['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(User::query()->count())->toBe(0)
        ->and(Account::query()->count())->toBe(0)
        ->and(AccountMember::query()->count())->toBe(0)
        ->and(Role::query()->count())->toBe(0);
});

test('registration rolls back when account authorization bootstrap fails', function () {
    AccountMember::creating(function (): void {
        throw new RuntimeException('Simulated membership bootstrap failure');
    });

    try {
        registerFromStatefulOrigin()->assertStatus(500);
    } finally {
        AccountMember::flushEventListeners();
    }

    expect(User::query()->count())->toBe(0)
        ->and(Account::query()->count())->toBe(0)
        ->and(AccountMember::query()->count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
});
