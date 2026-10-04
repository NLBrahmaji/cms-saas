<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\User;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function authenticatedUserOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncAuthenticatedUserCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetJsonForUser(string $uri): TestResponse
{
    $response = test()->withHeaders(authenticatedUserOriginHeaders())->getJson($uri);

    syncAuthenticatedUserCookies($response);

    return $response;
}

function statefulPostJsonForUser(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(authenticatedUserOriginHeaders())->postJson($uri, $data);

    syncAuthenticatedUserCookies($response);

    return $response;
}

function createUserForAuthenticatedUserTests(): User
{
    $user = User::query()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);

    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => $user->name,
        'status' => 'active',
    ]);

    AccountMember::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    return $user;
}

function expectedAuthenticatedUserPayload(User $user): array
{
    return [
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ],
    ];
}

test('authenticated user receives the controlled user response', function () {
    $user = createUserForAuthenticatedUserTests();

    statefulPostJsonForUser('/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'Str0ngPass!',
    ])->assertOk();

    statefulGetJsonForUser('/user')
        ->assertOk()
        ->assertExactJson(expectedAuthenticatedUserPayload($user));
});

test('authenticated user response exposes only the expected user fields', function () {
    createUserForAuthenticatedUserTests();

    statefulPostJsonForUser('/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'Str0ngPass!',
    ])->assertOk();

    $response = statefulGetJsonForUser('/user')->assertOk();

    expect(array_keys($response->json()))->toBe(['user']);
    expect(array_keys($response->json('user')))->toEqual(['id', 'name', 'email']);
    expect($response->json())->not->toHaveKey('password');
    expect($response->json('user'))->not->toHaveKey('password');
    expect($response->json('user'))->not->toHaveKey('remember_token');
    expect($response->json('user'))->not->toHaveKey('email_verified_at');
    expect($response->json('user'))->not->toHaveKey('created_at');
    expect($response->json('user'))->not->toHaveKey('updated_at');
});

test('unauthenticated user request receives unauthorized', function () {
    statefulGetJsonForUser('/user')
        ->assertUnauthorized();
});

test('login user response contract is unchanged', function () {
    $user = createUserForAuthenticatedUserTests();

    statefulPostJsonForUser('/auth/login', [
        'email' => 'ada@example.com',
        'password' => 'Str0ngPass!',
    ])
        ->assertOk()
        ->assertExactJson(expectedAuthenticatedUserPayload($user));
});

test('registration user response contract is unchanged', function () {
    $response = statefulPostJsonForUser('/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'Str0ngPass!',
        'password_confirmation' => 'Str0ngPass!',
    ]);

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

    $response
        ->assertCreated()
        ->assertJsonPath('user', expectedAuthenticatedUserPayload($user)['user'])
        ->assertJsonStructure([
            'account' => ['id', 'name', 'status'],
        ]);
});
