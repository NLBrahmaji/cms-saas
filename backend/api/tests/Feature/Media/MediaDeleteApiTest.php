<?php

use App\Models\Account;
use App\Models\AccountMember;
use App\Models\Media;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\PageVersionSeoSetting;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function mediaDeleteUri(Account $account, Website $website, Media $media): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/media/'.$media->id;
}

function mediaDeleteListUri(Account $account, Website $website): string
{
    return '/v1/accounts/'.$account->id.'/websites/'.$website->id.'/media';
}

function mediaDeleteHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

function syncMediaDeleteCookies(TestResponse $response): void
{
    foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $cookieName) {
        $cookie = $response->getCookie($cookieName);

        if ($cookie !== null) {
            test()->withUnencryptedCookie($cookieName, $cookie->getValue());
        }
    }

    Auth::forgetGuards();
}

function statefulDeleteMedia(string $uri): TestResponse
{
    $response = test()->withHeaders(mediaDeleteHeaders())->deleteJson($uri);

    syncMediaDeleteCookies($response);

    return $response;
}

function statefulGetMediaDelete(string $uri): TestResponse
{
    $response = test()->withHeaders(mediaDeleteHeaders())->getJson($uri);

    syncMediaDeleteCookies($response);

    return $response;
}

function statefulPatchMediaDelete(string $uri, array $data): TestResponse
{
    $response = test()->withHeaders(mediaDeleteHeaders())->patchJson($uri, $data);

    syncMediaDeleteCookies($response);

    return $response;
}

function statefulPostMediaDeleteLogin(array $data): TestResponse
{
    $response = test()->withHeaders(mediaDeleteHeaders())->postJson('/v1/auth/login', $data);

    syncMediaDeleteCookies($response);

    return $response;
}

function createMediaDeleteUser(): User
{
    return User::query()->create([
        'name' => 'Delete User',
        'email' => 'media-delete-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function loginMediaDeleteUser(User $user): void
{
    statefulPostMediaDeleteLogin([
        'email' => $user->email,
        'password' => 'Str0ngPass!',
    ])->assertOk();
}

function attachMediaDeleteMembership(User $user, array $permissions): Account
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

function createMediaDeleteWebsite(Account $account, string $prefix = 'site'): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site Name',
        'subdomain' => $prefix.'-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function createMediaDeleteMedia(Website $website, User $user, array $overrides = []): Media
{
    $path = $overrides['path'] ?? 'websites/'.$website->id.'/media/'.uniqid().'.jpg';

    if (! array_key_exists('path', $overrides)) {
        Storage::disk('public')->put($path, 'stored-image-bytes');
    }

    return Media::query()->create(array_merge([
        'website_id' => $website->id,
        'disk' => 'public',
        'path' => $path,
        'original_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'size' => 18,
        'width' => 10,
        'height' => 10,
        'alt_text' => null,
        'title' => null,
        'source' => 'upload',
        'created_by' => $user->id,
    ], $overrides));
}

test('unauthenticated media delete is unauthorized', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.delete']);
    $website = createMediaDeleteWebsite($account);
    $media = createMediaDeleteMedia($website, $user);

    statefulDeleteMedia(mediaDeleteUri($account, $website, $media))->assertUnauthorized();

    expect($media->fresh())->not->toBeNull()
        ->and($media->trashed())->toBeFalse();
});

test('member with media delete can soft delete without media view', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.delete']);
    $website = createMediaDeleteWebsite($account);
    $media = createMediaDeleteMedia($website, $user);
    $path = $media->path;

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteUri($account, $website, $media))->assertNoContent();

    $this->assertSoftDeleted('media', ['id' => $media->id]);
    expect(Media::withTrashed()->find($media->id))->not->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeTrue()
        ->and(Storage::disk('public')->get($path))->toBe('stored-image-bytes');
});

test('media view permission alone forbids delete', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.view']);
    $website = createMediaDeleteWebsite($account);
    $media = createMediaDeleteMedia($website, $user);

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteUri($account, $website, $media))->assertForbidden();

    expect($media->fresh()->trashed())->toBeFalse();
});

test('media upload permission alone forbids delete', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.upload']);
    $website = createMediaDeleteWebsite($account);
    $media = createMediaDeleteMedia($website, $user);

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteUri($account, $website, $media))->assertForbidden();
});

test('media update permission alone forbids delete', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.update']);
    $website = createMediaDeleteWebsite($account);
    $media = createMediaDeleteMedia($website, $user);

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteUri($account, $website, $media))->assertForbidden();
});

test('website update permission alone forbids delete', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['website.update']);
    $website = createMediaDeleteWebsite($account);
    $media = createMediaDeleteMedia($website, $user);

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteUri($account, $website, $media))->assertForbidden();
});

test('media delete returns not found for wrong website nesting', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.delete']);
    $websiteA = createMediaDeleteWebsite($account, 'a');
    $websiteB = createMediaDeleteWebsite($account, 'b');
    $media = createMediaDeleteMedia($websiteB, $user);

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteUri($account, $websiteA, $media))->assertNotFound();

    expect($media->fresh()->trashed())->toBeFalse();
});

test('media delete returns not found for wrong account website', function () {
    $user = createMediaDeleteUser();
    $accountA = attachMediaDeleteMembership($user, ['media.delete']);
    $otherUser = createMediaDeleteUser();
    $accountB = attachMediaDeleteMembership($otherUser, ['media.delete']);
    $websiteOnB = createMediaDeleteWebsite($accountB);
    $media = createMediaDeleteMedia($websiteOnB, $otherUser);

    loginMediaDeleteUser($user);

    statefulDeleteMedia('/v1/accounts/'.$accountA->id.'/websites/'.$websiteOnB->id.'/media/'.$media->id)
        ->assertNotFound();

    expect($media->fresh()->trashed())->toBeFalse();
});

test('media delete returns not found for unknown media id', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.delete']);
    $website = createMediaDeleteWebsite($account);

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteListUri($account, $website).'/999999')->assertNotFound();
});

test('media delete returns not found when media is already soft deleted', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.delete']);
    $website = createMediaDeleteWebsite($account);
    $media = createMediaDeleteMedia($website, $user);
    $path = $media->path;

    $media->delete();

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteUri($account, $website, $media))->assertNotFound();

    expect(Storage::disk('public')->exists($path))->toBeTrue();
});

test('deleted media is hidden from show list and patch', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.delete', 'media.view', 'media.update']);
    $website = createMediaDeleteWebsite($account);
    $media = createMediaDeleteMedia($website, $user);
    $other = createMediaDeleteMedia($website, $user);

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteUri($account, $website, $media))->assertNoContent();

    statefulGetMediaDelete(mediaDeleteUri($account, $website, $media))->assertNotFound();

    statefulGetMediaDelete(mediaDeleteListUri($account, $website))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $other->id);

    statefulPatchMediaDelete(mediaDeleteUri($account, $website, $media), [
        'title' => 'Nope',
    ])->assertNotFound();
});

test('soft deleting referenced media preserves foreign key and stored file', function () {
    $user = createMediaDeleteUser();
    $account = attachMediaDeleteMembership($user, ['media.delete']);
    $website = createMediaDeleteWebsite($account);
    $media = createMediaDeleteMedia($website, $user);
    $path = $media->path;

    $page = Page::query()->create(['website_id' => $website->id]);
    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'Home',
        'slug' => 'home',
        'created_by' => $user->id,
    ]);
    $page->assignDraftVersion($version);

    PageVersionSeoSetting::query()->create([
        'page_version_id' => $version->id,
        'og_image_id' => $media->id,
    ]);

    loginMediaDeleteUser($user);

    statefulDeleteMedia(mediaDeleteUri($account, $website, $media))->assertNoContent();

    $this->assertSoftDeleted('media', ['id' => $media->id]);

    expect(PageVersionSeoSetting::query()->where('page_version_id', $version->id)->value('og_image_id'))
        ->toBe($media->id)
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

test('media deleter production code does not use storage facade', function () {
    $source = file_get_contents(base_path('app/Support/Media/MediaDeleter.php'));

    expect($source)->not->toContain('Storage::');
});
