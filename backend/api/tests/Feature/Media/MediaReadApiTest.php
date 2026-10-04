<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Media;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
});

function mediaReadOriginHeaders(): array
{
    return ['Origin' => 'http://localhost:3001'];
}

function syncMediaReadCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulGetMediaRead(string $uri): TestResponse
{
    $response = test()->withHeaders(mediaReadOriginHeaders())->getJson($uri);

    syncMediaReadCookies($response);

    return $response;
}

function statefulPostMediaRead(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(mediaReadOriginHeaders())->postJson($uri, $data);

    syncMediaReadCookies($response);

    return $response;
}

function createMediaReadUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Media User',
        'email' => 'media-read-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginMediaReadUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostMediaRead('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachMediaReadMembership(
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
        'name' => 'member-'.uniqid(),
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

function createMediaReadWebsite(Account $account, string $subdomain = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => $subdomain.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function accountMediaUri(Account $account, Website $website, ?Media $media = null): string
{
    $uri = '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/media';

    if ($media !== null) {
        $uri .= '/'.$media->id;
    }

    return $uri;
}

/**
 * @return array<string, mixed>
 */
function mediaReadResourcePayload(Media $media): array
{
    return [
        'id' => $media->id,
        'original_name' => $media->original_name,
        'mime_type' => $media->mime_type,
        'extension' => $media->extension,
        'size' => $media->size,
        'width' => $media->width,
        'height' => $media->height,
        'alt_text' => $media->alt_text,
        'title' => $media->title,
        'source' => $media->source,
        'url' => Storage::disk($media->disk)->url($media->path),
        'created_at' => $media->created_at?->toJSON(),
        'updated_at' => $media->updated_at?->toJSON(),
    ];
}

function createMediaReadRecord(Website $website, User $user, array $overrides = []): Media
{
    return Media::query()->create(array_merge([
        'website_id' => $website->id,
        'disk' => 'public',
        'path' => 'images/'.uniqid().'.jpg',
        'original_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 2048,
        'width' => 800,
        'height' => 600,
        'alt_text' => 'Alt text',
        'title' => 'Title',
        'source' => 'upload',
        'created_by' => $user->id,
    ], $overrides));
}

test('account permission seeder includes dedicated media permissions', function () {
    $expected = [
        'media.view',
        'media.upload',
        'media.update',
        'media.delete',
    ];

    foreach ($expected as $permission) {
        expect(Permission::query()->where('name', $permission)->exists())->toBeTrue();
    }

    expect(AccountPermissionSeeder::PERMISSIONS)->toContain(...$expected);
});

test('media model relationships and soft delete exclusion from website media relation', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account');
    $website = createMediaReadWebsite($account);

    $media = createMediaReadRecord($website, $user);

    $media->refresh()->load(['website', 'createdBy']);

    expect($media->website->id)->toBe($website->id)
        ->and($media->createdBy->id)->toBe($user->id);

    $website->load('media');

    expect($website->media->pluck('id')->all())->toBe([$media->id]);

    $media->delete();

    expect($website->media()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(Media::withTrashed()->count())->toBe(1);
});

test('unauthenticated media list and show requests are unauthorized', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account');
    $website = createMediaReadWebsite($account);
    $media = createMediaReadRecord($website, $user);

    statefulGetMediaRead(accountMediaUri($account, $website))->assertUnauthorized();
    statefulGetMediaRead(accountMediaUri($account, $website, $media))->assertUnauthorized();
});

test('active member with media view can list and show media', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $website = createMediaReadWebsite($account);
    $media = createMediaReadRecord($website, $user);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => [mediaReadResourcePayload($media)]]);

    statefulGetMediaRead(accountMediaUri($account, $website, $media))
        ->assertOk()
        ->assertExactJson(['data' => mediaReadResourcePayload($media)]);
});

test('media list returns empty collection for website without media', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $website = createMediaReadWebsite($account);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('media list orders by created_at desc then id desc', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $website = createMediaReadWebsite($account);

    $olderTime = Carbon::parse('2024-01-01 12:00:00');
    $newerTime = Carbon::parse('2024-06-01 12:00:00');

    $olderHighId = createMediaReadRecord($website, $user, [
        'original_name' => 'older-high.jpg',
        'created_at' => $olderTime,
        'updated_at' => $olderTime,
    ]);

    $olderLowId = createMediaReadRecord($website, $user, [
        'original_name' => 'older-low.jpg',
        'created_at' => $olderTime,
        'updated_at' => $olderTime,
    ]);

    if ($olderHighId->id < $olderLowId->id) {
        [$olderHighId, $olderLowId] = [$olderLowId, $olderHighId];
    }

    $newest = createMediaReadRecord($website, $user, [
        'original_name' => 'newest.jpg',
        'created_at' => $newerTime,
        'updated_at' => $newerTime,
    ]);

    loginMediaReadUser($user);

    $response = statefulGetMediaRead(accountMediaUri($account, $website))->assertOk();

    expect($response->json('data.*.id'))->toBe([
        $newest->id,
        $olderHighId->id,
        $olderLowId->id,
    ]);
});

test('media list excludes media belonging to another website on the same account', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $websiteA = createMediaReadWebsite($account, 'a');
    $websiteB = createMediaReadWebsite($account, 'b');

    $onA = createMediaReadRecord($websiteA, $user);
    createMediaReadRecord($websiteB, $user);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $websiteA))
        ->assertOk()
        ->assertExactJson(['data' => [mediaReadResourcePayload($onA)]]);
});

test('media list excludes media from websites in another account', function () {
    $user = createMediaReadUser();
    $accountA = attachMediaReadMembership($user, 'Account A', ['media.view']);
    $otherUser = createMediaReadUser();
    $accountB = attachMediaReadMembership($otherUser, 'Account B');
    $websiteA = createMediaReadWebsite($accountA);
    $websiteB = createMediaReadWebsite($accountB);

    $onA = createMediaReadRecord($websiteA, $user);
    createMediaReadRecord($websiteB, $otherUser);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($accountA, $websiteA))
        ->assertOk()
        ->assertExactJson(['data' => [mediaReadResourcePayload($onA)]]);
});

test('media list excludes soft deleted media', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $website = createMediaReadWebsite($account);

    $active = createMediaReadRecord($website, $user);
    $deleted = createMediaReadRecord($website, $user);
    $deleted->delete();

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website))
        ->assertOk()
        ->assertExactJson(['data' => [mediaReadResourcePayload($active)]]);
});

test('media show returns resource with nullable metadata fields', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $website = createMediaReadWebsite($account);

    $media = createMediaReadRecord($website, $user, [
        'extension' => null,
        'width' => null,
        'height' => null,
        'alt_text' => null,
        'title' => null,
    ]);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website, $media))
        ->assertOk()
        ->assertJsonPath('data.extension', null)
        ->assertJsonPath('data.width', null)
        ->assertJsonPath('data.height', null)
        ->assertJsonPath('data.alt_text', null)
        ->assertJsonPath('data.title', null);
});

test('media show returns not found for media belonging to another website', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $websiteA = createMediaReadWebsite($account, 'a');
    $websiteB = createMediaReadWebsite($account, 'b');

    $mediaOnB = createMediaReadRecord($websiteB, $user);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $websiteA, $mediaOnB))->assertNotFound();
});

test('website from another account cannot be used for media routes', function () {
    $user = createMediaReadUser();
    $accountA = attachMediaReadMembership($user, 'Account A', ['media.view']);
    $otherUser = createMediaReadUser();
    $accountB = attachMediaReadMembership($otherUser, 'Account B');
    $websiteOnB = createMediaReadWebsite($accountB);

    loginMediaReadUser($user);

    statefulGetMediaRead('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/media')
        ->assertNotFound();
});

test('media show returns not found for unknown id', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $website = createMediaReadWebsite($account);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website).'/999999')->assertNotFound();
});

test('media show returns not found for soft deleted media', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $website = createMediaReadWebsite($account);
    $media = createMediaReadRecord($website, $user);
    $media->delete();

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website, $media))->assertNotFound();
});

test('media resource does not expose storage or tenancy internals', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $website = createMediaReadWebsite($account);
    $media = createMediaReadRecord($website, $user);

    loginMediaReadUser($user);

    $payload = statefulGetMediaRead(accountMediaUri($account, $website, $media))
        ->assertOk()
        ->json('data');

    expect($payload)->toHaveKey('url')
        ->and($payload)->not->toHaveKeys([
            'website_id',
            'created_by',
            'deleted_at',
            'disk',
            'path',
            'public_url',
            'download_url',
            'thumbnail_url',
        ]);
});

test('website view permission alone does not authorize media read', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['website.view']);
    $website = createMediaReadWebsite($account);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website))->assertForbidden();
});

test('website update permission alone does not authorize media read', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['website.update']);
    $website = createMediaReadWebsite($account);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website))->assertForbidden();
});

test('media upload permission alone does not authorize media read', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.upload']);
    $website = createMediaReadWebsite($account);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website))->assertForbidden();
});

test('media update permission alone does not authorize media read', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.update']);
    $website = createMediaReadWebsite($account);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website))->assertForbidden();
});

test('media delete permission alone does not authorize media read', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.delete']);
    $website = createMediaReadWebsite($account);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website))->assertForbidden();
});

test('raw db inserted media remains readable through media api', function () {
    $user = createMediaReadUser();
    $account = attachMediaReadMembership($user, 'Account', ['media.view']);
    $website = createMediaReadWebsite($account);

    $mediaId = (int) DB::table('media')->insertGetId([
        'website_id' => $website->id,
        'disk' => 'public',
        'path' => 'images/hero.jpg',
        'original_name' => 'hero.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 1024,
        'source' => 'upload',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $media = Media::query()->findOrFail($mediaId);

    loginMediaReadUser($user);

    statefulGetMediaRead(accountMediaUri($account, $website, $media))
        ->assertOk()
        ->assertJsonPath('data.id', $mediaId)
        ->assertJsonPath('data.original_name', 'hero.jpg');
});
