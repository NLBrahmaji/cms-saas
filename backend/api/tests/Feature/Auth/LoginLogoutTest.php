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

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function statefulOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncStatefulCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGet(string $uri): TestResponse
{
    $response = test()->withHeaders(statefulOriginHeaders())->get($uri);

    syncStatefulCookies($response);

    return $response;
}

function statefulPostJson(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(statefulOriginHeaders())->postJson($uri, $data);

    syncStatefulCookies($response);

    return $response;
}

function statefulGetJson(string $uri): TestResponse
{
    $response = test()->withHeaders(statefulOriginHeaders())->getJson($uri);

    syncStatefulCookies($response);

    return $response;
}

function loginPayload(array $overrides = []): array
{
    return array_merge([
        'email' => 'ada@example.com',
        'password' => 'Str0ngPass!',
    ], $overrides);
}

function loginFromStatefulOrigin(array $payload = []): TestResponse
{
    return statefulPostJson('/auth/login', loginPayload($payload));
}

function createUserForLoginTests(): User
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

test('valid credentials authenticate the user and return the expected response contract', function () {
    createUserForLoginTests();

    $response = loginFromStatefulOrigin();

    $response
        ->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
        ])
        ->assertJsonPath('user.email', 'ada@example.com');

    $this->assertAuthenticated('web');
});

test('login does not return account context', function () {
    createUserForLoginTests();

    $response = loginFromStatefulOrigin();

    $response
        ->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
        ]);

    expect($response->json())->not->toHaveKey('account');
});

test('login regenerates the session', function () {
    createUserForLoginTests();

    $csrfResponse = statefulGet('/sanctum/csrf-cookie');
    $sessionCookieBeforeLogin = $csrfResponse->getCookie(config('session.cookie'))?->getValue();

    $loginResponse = loginFromStatefulOrigin();
    $loginResponse->assertOk();

    $sessionCookieAfterLogin = $loginResponse->getCookie(config('session.cookie'))?->getValue();

    expect($sessionCookieAfterLogin)->not->toBe($sessionCookieBeforeLogin);
});

test('invalid password is rejected without authenticating the user', function () {
    createUserForLoginTests();

    loginFromStatefulOrigin(['password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest('web');
});

test('unknown email is rejected with the same authentication error message', function () {
    createUserForLoginTests();

    $invalidPasswordResponse = loginFromStatefulOrigin(['password' => 'wrong-password']);
    $unknownEmailResponse = loginFromStatefulOrigin(['email' => 'missing@example.com']);

    $unknownEmailResponse
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($unknownEmailResponse->json('errors.email.0'))
        ->toBe($invalidPasswordResponse->json('errors.email.0'));

    $this->assertGuest('web');
});

test('login validation errors do not authenticate the user', function () {
    loginFromStatefulOrigin(['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->assertGuest('web');
});

test('login does not create bearer tokens', function () {
    createUserForLoginTests();

    loginFromStatefulOrigin()->assertOk();

    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

test('authenticated user can logout', function () {
    createUserForLoginTests();
    loginFromStatefulOrigin()->assertOk();

    statefulPostJson('/auth/logout')
        ->assertOk()
        ->assertJson(['message' => 'Logged out.']);
});

test('logout clears authentication and invalidates the session', function () {
    createUserForLoginTests();
    $loginResponse = loginFromStatefulOrigin();
    $loginResponse->assertOk();

    $sessionCookieWhileAuthenticated = $loginResponse->getCookie(config('session.cookie'))?->getValue();

    $logoutResponse = statefulPostJson('/auth/logout');
    $logoutResponse->assertOk();

    $sessionCookieAfterLogout = $logoutResponse->getCookie(config('session.cookie'))?->getValue();

    expect($sessionCookieAfterLogout)->not->toBe($sessionCookieWhileAuthenticated);

    statefulGetJson('/user')->assertUnauthorized();
});

test('logout regenerates the csrf token', function () {
    createUserForLoginTests();
    $loginResponse = loginFromStatefulOrigin();
    $loginResponse->assertOk();

    $xsrfBeforeLogout = $loginResponse->getCookie('XSRF-TOKEN')?->getValue();

    $logoutResponse = statefulPostJson('/auth/logout');
    $logoutResponse->assertOk();

    $xsrfAfterLogout = $logoutResponse->getCookie('XSRF-TOKEN')?->getValue();

    expect($xsrfAfterLogout)->not->toBe($xsrfBeforeLogout);
});

test('unauthenticated user cannot access user endpoint', function () {
    statefulGetJson('/user')
        ->assertUnauthorized();
});

test('unauthenticated logout is rejected', function () {
    statefulPostJson('/auth/logout')
        ->assertUnauthorized();
});

test('protected auth routes reject requests after logging out', function () {
    createUserForLoginTests();
    loginFromStatefulOrigin()->assertOk();

    statefulPostJson('/auth/logout')->assertOk();

    statefulGetJson('/user')->assertUnauthorized();
});
