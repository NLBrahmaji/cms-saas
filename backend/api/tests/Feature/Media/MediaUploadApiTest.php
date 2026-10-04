<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Media;
use App\Models\User;
use App\Models\Website;
use App\Support\Media\MediaUploader;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Contracts\Filesystem\Filesystem;
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

afterEach(function () {
    Mockery::close();
});

function mediaUploadOriginHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

function syncMediaUploadCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulPostMediaUpload(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(mediaUploadOriginHeaders())->post($uri, $data);

    syncMediaUploadCookies($response);

    return $response;
}

function statefulPostMediaUploadLogin(string $uri, array $data = []): TestResponse
{
    $response = test()->withHeaders(mediaUploadOriginHeaders())->postJson($uri, $data);

    syncMediaUploadCookies($response);

    return $response;
}

function createMediaUploadUser(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'name' => 'Media Upload User',
        'email' => 'media-upload-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ], $overrides));
}

function loginMediaUploadUser(User $user, string $password = 'Str0ngPass!'): void
{
    statefulPostMediaUploadLogin('/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->assertOk();
}

/**
 * @param  list<string>  $permissions
 */
function attachMediaUploadMembership(
    User $user,
    string $accountName,
    array $permissions,
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

function createMediaUploadWebsite(Account $account, string $subdomain = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Example Site',
        'subdomain' => $subdomain.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function accountMediaUploadUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/media';
}

function tinyGifBytes(): string
{
    return base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}

function tinyWebpBytes(): string
{
    return base64_decode('UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAgA0JaQAA3AA/vuUAAA=');
}

function assertSuccessfulRasterUpload(
    TestResponse $response,
    Website $website,
    User $user,
    string $expectedMime,
    string $expectedExtension,
    string $expectedOriginalName,
): Media {
    $response->assertCreated();

    $media = Media::query()->firstOrFail();

    expect($media->website_id)->toBe($website->id)
        ->and($media->disk)->toBe('public')
        ->and($media->path)->toStartWith('websites/'.$website->id.'/media/')
        ->and($media->path)->toEndWith('.'.$expectedExtension)
        ->and($media->original_name)->toBe($expectedOriginalName)
        ->and($media->mime_type)->toBe($expectedMime)
        ->and($media->extension)->toBe($expectedExtension)
        ->and($media->size)->toBeGreaterThan(0)
        ->and($media->width)->toBeGreaterThan(0)
        ->and($media->height)->toBeGreaterThan(0)
        ->and($media->alt_text)->toBeNull()
        ->and($media->title)->toBeNull()
        ->and($media->source)->toBe('upload')
        ->and($media->created_by)->toBe($user->id);

    Storage::disk('public')->assertExists($media->path);

    $payload = $response->json('data');

    expect($payload)->toHaveKeys(['id', 'url', 'mime_type', 'extension'])
        ->and($payload)->not->toHaveKeys(['disk', 'path'])
        ->and($payload['url'])->toBe(Storage::disk('public')->url($media->path));

    return $media;
}

test('unauthenticated media upload is unauthorized', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertUnauthorized();

    expect(Media::query()->count())->toBe(0);
});

test('member with media upload can upload without media view', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    $response = statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpg', 120, 80),
    ]);

    assertSuccessfulRasterUpload(
        $response,
        $website,
        $user,
        'image/jpeg',
        'jpg',
        'photo.jpg',
    );
});

test('media view permission alone forbids upload', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.view']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertForbidden();

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('media update permission alone forbids upload', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.update']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertForbidden();
});

test('media delete permission alone forbids upload', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.delete']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertForbidden();
});

test('website update permission alone forbids upload', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['website.update']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertForbidden();
});

test('upload rejects wrong account website nesting with not found', function () {
    $user = createMediaUploadUser();
    $accountA = attachMediaUploadMembership($user, 'Account A', ['media.upload']);
    $otherUser = createMediaUploadUser();
    $accountB = attachMediaUploadMembership($otherUser, 'Account B', ['media.upload']);
    $websiteOnB = createMediaUploadWebsite($accountB);

    loginMediaUploadUser($user);

    statefulPostMediaUpload('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/media', [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertNotFound();
});

test('upload stores jpeg png webp and gif successfully', function (string $label, callable $fileFactory, string $mime, string $extension) {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    $file = $fileFactory();

    $response = statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => $file,
    ]);

    assertSuccessfulRasterUpload($response, $website, $user, $mime, $extension, $file->getClientOriginalName());
})->with([
    'jpeg' => [
        'jpeg',
        fn () => UploadedFile::fake()->image('photo.jpeg', 50, 40),
        'image/jpeg',
        'jpg',
    ],
    'png' => [
        'png',
        fn () => UploadedFile::fake()->image('graphic.png', 30, 20),
        'image/png',
        'png',
    ],
    'webp' => [
        'webp',
        fn () => UploadedFile::fake()->createWithContent('image.webp', tinyWebpBytes(), 'image/webp'),
        'image/webp',
        'webp',
    ],
    'gif' => [
        'gif',
        fn () => UploadedFile::fake()->createWithContent('animation.gif', tinyGifBytes(), 'image/gif'),
        'image/gif',
        'gif',
    ],
]);

test('upload normalizes jpeg extension when client filename uses jpeg suffix', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    $response = statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpeg', 10, 10),
    ]);

    $media = assertSuccessfulRasterUpload($response, $website, $user, 'image/jpeg', 'jpg', 'photo.jpeg');

    expect($media->path)->toEndWith('.jpg');
});

test('upload accepts jpeg content with misleading png filename', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    $jpeg = UploadedFile::fake()->image('real.jpg', 12, 12);
    $misleading = UploadedFile::fake()->createWithContent(
        'misleading.png',
        (string) file_get_contents($jpeg->getRealPath()),
    );

    $response = statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => $misleading,
    ]);

    assertSuccessfulRasterUpload($response, $website, $user, 'image/jpeg', 'jpg', 'misleading.png');
});

test('upload validation rejects missing null and non file values', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [])->assertUnprocessable();
    statefulPostMediaUpload(accountMediaUploadUri($account, $website), ['file' => null])->assertUnprocessable();
    statefulPostMediaUpload(accountMediaUploadUri($account, $website), ['file' => 'not-a-file'])->assertUnprocessable();

    expect(Media::query()->count())->toBe(0);
});

test('upload validation rejects oversized svg pdf text unsupported mime and unknown fields', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    $oversized = UploadedFile::fake()->create('big.jpg', 10241);
    statefulPostMediaUpload(accountMediaUploadUri($account, $website), ['file' => $oversized])->assertUnprocessable();

    $svg = UploadedFile::fake()->createWithContent(
        'icon.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        'image/svg+xml',
    );
    statefulPostMediaUpload(accountMediaUploadUri($account, $website), ['file' => $svg])->assertUnprocessable();

    $pdf = UploadedFile::fake()->createWithContent('doc.pdf', '%PDF-1.4', 'application/pdf');
    statefulPostMediaUpload(accountMediaUploadUri($account, $website), ['file' => $pdf])->assertUnprocessable();

    $text = UploadedFile::fake()->createWithContent('notes.txt', 'hello', 'text/plain');
    statefulPostMediaUpload(accountMediaUploadUri($account, $website), ['file' => $text])->assertUnprocessable();

    $zip = UploadedFile::fake()->createWithContent('archive.zip', 'PK', 'application/zip');
    statefulPostMediaUpload(accountMediaUploadUri($account, $website), ['file' => $zip])->assertUnprocessable();

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpg'),
        'alt_text' => 'nope',
    ])->assertUnprocessable();

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('upload creates independent rows for duplicate uploads', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    $file = UploadedFile::fake()->image('same.jpg', 10, 10);

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), ['file' => $file])->assertCreated();
    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('same.jpg', 10, 10),
    ])->assertCreated();

    expect(Media::query()->count())->toBe(2)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(2);
});

test('upload compensates stored file when media persistence fails', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    Media::creating(function (): void {
        throw new RuntimeException('Simulated media persistence failure');
    });

    try {
        statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
            'file' => UploadedFile::fake()->image('photo.jpg', 10, 10),
        ])->assertStatus(500);
    } finally {
        Media::flushEventListeners();
    }

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('upload does not persist media when storage put fails', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    loginMediaUploadUser($user);

    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('exists')->andReturn(false);
    $disk->shouldReceive('put')->once()->andReturn(false);

    Storage::shouldReceive('disk')->with('public')->andReturn($disk);

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpg', 10, 10),
    ])->assertStatus(500);

    expect(Media::query()->count())->toBe(0);
});

test('upload does not overwrite an existing file at the generated path', function () {
    $user = createMediaUploadUser();
    $account = attachMediaUploadMembership($user, 'Account', ['media.upload']);
    $website = createMediaUploadWebsite($account);

    $firstUuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $secondUuid = 'bbbbbbbb-bbbb-cccc-dddd-eeeeeeeeeeee';
    $uuids = [$firstUuid, $secondUuid];
    $index = 0;

    app()->instance(MediaUploader::class, new MediaUploader(function () use (&$index, $uuids): string {
        return $uuids[$index++];
    }));

    $collisionPath = sprintf('websites/%d/media/%s.jpg', $website->id, $firstUuid);
    Storage::disk('public')->put($collisionPath, 'existing-bytes');

    loginMediaUploadUser($user);

    statefulPostMediaUpload(accountMediaUploadUri($account, $website), [
        'file' => UploadedFile::fake()->image('photo.jpg', 10, 10),
    ])->assertCreated();

    expect(Storage::disk('public')->get($collisionPath))->toBe('existing-bytes');

    $media = Media::query()->firstOrFail();

    expect($media->path)->toBe(sprintf('websites/%d/media/%s.jpg', $website->id, $secondUuid));
});
