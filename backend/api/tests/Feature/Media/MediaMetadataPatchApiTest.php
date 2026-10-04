<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Media;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AccountPermissionSeeder::class);
    $this->withCredentials();
    Storage::fake('public');
});

function mediaMetadataPatchUri(Account $account, Website $website, Media $media): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/media/'.$media->id;
}

function mediaMetadataPatchHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

function syncMediaMetadataPatchCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPatchMediaMetadata(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(mediaMetadataPatchHeaders())->patchJson($uri, $data);

    syncMediaMetadataPatchCookies($response);

    return $response;
}

function statefulPostMediaMetadataLogin(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(mediaMetadataPatchHeaders())->postJson($uri, $data);

    syncMediaMetadataPatchCookies($response);

    return $response;
}

function statefulPostMediaMetadataUpload(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(mediaMetadataPatchHeaders())->post($uri, $data);

    syncMediaMetadataPatchCookies($response);

    return $response;
}

function statefulGetMediaMetadata(string $uri): TestResponse
{
    $response = test()->withHeaders(mediaMetadataPatchHeaders())->getJson($uri);

    syncMediaMetadataPatchCookies($response);

    return $response;
}

function createMediaMetadataPatchUser(): User
{
    return User::query()->create([
        'name' => 'Metadata User',
        'email' => 'media-meta-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function loginMediaMetadataPatchUser(User $user): void
{
    statefulPostMediaMetadataLogin('/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function attachMediaMetadataPatchMembership(User $user, array $permissions): Account
{
    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => 'Account',
        'status' => 'active',
    ]);

    AccountMember::query()->create([
        'account_id' => $account->id,
        'user_id' => $user->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $role = Role::query()->create([
        'name' => 'role-'.uniqid(),
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

function createMediaMetadataPatchWebsite(Account $account, string $prefix = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site Name',
        'subdomain' => $prefix.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function createMediaMetadataPatchMedia(Website $website, User $user, array $overrides = []): Media
{
    $path = $overrides['path'] ?? 'websites/'.$website->id.'/media/'.uniqid().'.jpg';

    if (! array_key_exists('path', $overrides)) {
        Storage::disk('public')->put($path, 'image-bytes');
    }

    return Media::query()->create(array_merge([
        'website_id' => $website->id,
        'disk' => 'public',
        'path' => $path,
        'original_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 11,
        'width' => 10,
        'height' => 10,
        'alt_text' => 'Old alt',
        'title' => 'Old title',
        'source' => 'upload',
        'created_by' => $user->id,
    ], $overrides));
}

function accountMediaMetadataListUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/media';
}

test('unauthenticated media metadata patch is unauthorized', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => 'New',
    ])->assertUnauthorized();
});

test('member with media update can patch without media view', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => 'Accessible alt',
    ])
        ->assertOk()
        ->assertJsonPath('data.alt_text', 'Accessible alt');
});

test('media view permission alone forbids metadata patch', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.view']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'title' => 'Nope',
    ])->assertForbidden();
});

test('media upload permission alone forbids metadata patch', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.upload']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'title' => 'Nope',
    ])->assertForbidden();
});

test('media delete permission alone forbids metadata patch', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.delete']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'title' => 'Nope',
    ])->assertForbidden();
});

test('website update permission alone forbids metadata patch', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['website.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'title' => 'Nope',
    ])->assertForbidden();
});

test('media metadata patch returns not found for wrong website nesting', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $websiteA = createMediaMetadataPatchWebsite($account, 'a');
    $websiteB = createMediaMetadataPatchWebsite($account, 'b');
    $media = createMediaMetadataPatchMedia($websiteB, $user);

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $websiteA, $media), [
        'alt_text' => 'New',
    ])->assertNotFound();
});

test('media metadata patch returns not found for wrong account website', function () {
    $user = createMediaMetadataPatchUser();
    $accountA = attachMediaMetadataPatchMembership($user, ['media.update']);
    $otherUser = createMediaMetadataPatchUser();
    $accountB = attachMediaMetadataPatchMembership($otherUser, ['media.update']);
    $websiteOnB = createMediaMetadataPatchWebsite($accountB);
    $media = createMediaMetadataPatchMedia($websiteOnB, $otherUser);

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/media/'.$media->id, [
        'alt_text' => 'New',
    ])->assertNotFound();
});

test('media metadata patch returns not found for soft deleted media', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);
    $media->delete();

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => 'New',
    ])->assertNotFound();
});

test('media metadata patch validates request shape', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    $uri = mediaMetadataPatchUri($account, $website, $media);

    statefulPatchMediaMetadata($uri, [])->assertUnprocessable();

    statefulPatchMediaMetadata($uri, [
        'alt_text' => str_repeat('a', 501),
    ])->assertUnprocessable()->assertJsonValidationErrors(['alt_text']);

    statefulPatchMediaMetadata($uri, [
        'title' => str_repeat('b', 256),
    ])->assertUnprocessable()->assertJsonValidationErrors(['title']);

    statefulPatchMediaMetadata($uri, ['alt_text' => 123])->assertUnprocessable();
    statefulPatchMediaMetadata($uri, ['title' => false])->assertUnprocessable();

    statefulPatchMediaMetadata($uri, ['path' => 'other.jpg'])->assertUnprocessable();
    statefulPatchMediaMetadata($uri, [
        'alt_text' => 'Photo',
        'disk' => 's3',
    ])->assertUnprocessable();

    statefulPatchMediaMetadata($uri, ['disk' => 'public'])->assertUnprocessable();
    statefulPatchMediaMetadata($uri, ['mime_type' => 'image/png'])->assertUnprocessable();
});

test('media metadata patch updates alt text only and preserves title', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => 'New alt',
    ])
        ->assertOk()
        ->assertJsonPath('data.alt_text', 'New alt')
        ->assertJsonPath('data.title', 'Old title');

    expect($media->refresh()->alt_text)->toBe('New alt')
        ->and($media->title)->toBe('Old title');
});

test('media metadata patch updates title only and preserves alt text', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'title' => 'New title',
    ])
        ->assertOk()
        ->assertJsonPath('data.alt_text', 'Old alt')
        ->assertJsonPath('data.title', 'New title');
});

test('media metadata patch clears nullable fields independently', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    $uri = mediaMetadataPatchUri($account, $website, $media);

    statefulPatchMediaMetadata($uri, ['alt_text' => null])
        ->assertOk()
        ->assertJsonPath('data.alt_text', null)
        ->assertJsonPath('data.title', 'Old title');

    statefulPatchMediaMetadata($uri, ['title' => null])
        ->assertOk()
        ->assertJsonPath('data.alt_text', null)
        ->assertJsonPath('data.title', null);

    $media = createMediaMetadataPatchMedia($website, $user, [
        'alt_text' => 'Alt',
        'title' => 'Title',
    ]);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => null,
        'title' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.alt_text', null)
        ->assertJsonPath('data.title', null);
});

test('media metadata patch semantic no-op preserves updated at', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user, [
        'alt_text' => 'Hero image',
        'title' => 'Homepage hero',
    ]);

    loginMediaMetadataPatchUser($user);

    $uri = mediaMetadataPatchUri($account, $website, $media);
    $updatedAt = $media->updated_at;

    statefulPatchMediaMetadata($uri, ['alt_text' => 'Hero image'])->assertOk();
    expect($media->refresh()->updated_at->eq($updatedAt))->toBeTrue();

    statefulPatchMediaMetadata($uri, ['title' => 'Homepage hero'])->assertOk();
    expect($media->refresh()->updated_at->eq($updatedAt))->toBeTrue();

    statefulPatchMediaMetadata($uri, [
        'alt_text' => 'Hero image',
        'title' => 'Homepage hero',
    ])->assertOk();
    expect($media->refresh()->updated_at->eq($updatedAt))->toBeTrue();
});

test('media metadata patch null to null is semantic no-op', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user, [
        'alt_text' => null,
        'title' => null,
    ]);

    loginMediaMetadataPatchUser($user);

    $updatedAt = $media->updated_at;

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => null,
        'title' => null,
    ])->assertOk();

    expect($media->refresh()->updated_at->eq($updatedAt))->toBeTrue();
});

test('media metadata patch meaningful change updates updated at', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    loginMediaMetadataPatchUser($user);

    $updatedAt = $media->updated_at;

    sleep(1);

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => 'Changed',
    ])->assertOk();

    expect($media->refresh()->updated_at->gt($updatedAt))->toBeTrue();
});

test('media metadata patch does not mutate storage metadata or filesystem', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, ['media.update']);
    $website = createMediaMetadataPatchWebsite($account);
    $media = createMediaMetadataPatchMedia($website, $user);

    $before = $media->only([
        'website_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'source',
        'created_by',
    ]);

    loginMediaMetadataPatchUser($user);

    $urlBefore = statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => 'Before url',
    ])->json('data.url');

    $response = statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => 'After metadata',
        'title' => 'New title',
    ])->assertOk();

    $media->refresh();

    expect($media->only([
        'website_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'source',
        'created_by',
    ]))->toBe($before)
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue()
        ->and(Storage::disk('public')->get($media->path))->toBe('image-bytes')
        ->and(Storage::disk('public')->allFiles())->toHaveCount(1)
        ->and($response->json('data.url'))->toBe($urlBefore);
});

test('upload patch and get expose updated metadata with stable file fields', function () {
    $user = createMediaMetadataPatchUser();
    $account = attachMediaMetadataPatchMembership($user, [
        'media.upload',
        'media.update',
        'media.view',
    ]);
    $website = createMediaMetadataPatchWebsite($account);

    loginMediaMetadataPatchUser($user);

    $uploadResponse = statefulPostMediaMetadataUpload(accountMediaMetadataListUri($account, $website), [
        'file' => UploadedFile::fake()->image('hero.jpeg', 40, 30),
    ])->assertCreated();

    expect($uploadResponse->json('data.alt_text'))->toBeNull()
        ->and($uploadResponse->json('data.title'))->toBeNull();

    $media = Media::query()->firstOrFail();
    $fileUrl = $uploadResponse->json('data.url');
    $filePath = $media->path;

    statefulPatchMediaMetadata(mediaMetadataPatchUri($account, $website, $media), [
        'alt_text' => 'Hero alt',
        'title' => 'Hero title',
    ])
        ->assertOk()
        ->assertJsonPath('data.alt_text', 'Hero alt')
        ->assertJsonPath('data.title', 'Hero title')
        ->assertJsonPath('data.url', $fileUrl)
        ->assertJsonPath('data.mime_type', 'image/jpeg');

    statefulGetMediaMetadata(mediaMetadataPatchUri($account, $website, $media))
        ->assertOk()
        ->assertJsonPath('data.alt_text', 'Hero alt')
        ->assertJsonPath('data.title', 'Hero title')
        ->assertJsonPath('data.url', $fileUrl)
        ->assertJsonPath('data.original_name', 'hero.jpeg');

    expect(Storage::disk('public')->exists($filePath))->toBeTrue();
});
