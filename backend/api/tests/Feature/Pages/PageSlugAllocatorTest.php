<?php

use App\Models\Account;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\Website;
use App\Support\Page\PageSlugAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function createAllocatorAccount(): Account
{
    $user = User::query()->create([
        'name' => 'Owner',
        'email' => 'owner-'.uniqid().'@example.com',
        'password' => Hash::make('password'),
    ]);

    return Account::query()->create([
        'owner_id' => $user->id,
        'name' => 'Account',
        'status' => 'active',
    ]);
}

function createAllocatorWebsite(Account $account, string $subdomain): Website
{
    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site',
        'subdomain' => $subdomain,
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function attachDraftSlug(Website $website, string $slug): void
{
    $page = Page::query()->create([
        'website_id' => $website->id,
    ]);

    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'Existing',
        'slug' => $slug,
    ]);

    $page->update(['draft_version_id' => $version->id]);
}

test('slug allocator generates base slug from name', function () {
    $allocator = new PageSlugAllocator;
    $account = createAllocatorAccount();
    $website = createAllocatorWebsite($account, 'site-a');

    expect($allocator->allocate($website, 'About Us'))->toBe('about-us');
});

test('slug allocator increments slug within the same website', function () {
    $allocator = new PageSlugAllocator;
    $account = createAllocatorAccount();
    $website = createAllocatorWebsite($account, 'site-a');

    attachDraftSlug($website, 'about-us');

    expect($allocator->allocate($website, 'About Us'))->toBe('about-us-2');

    attachDraftSlug($website, 'about-us-2');

    expect($allocator->allocate($website, 'About Us'))->toBe('about-us-3');
});

test('slug allocator does not treat slugs in another website as collisions', function () {
    $allocator = new PageSlugAllocator;
    $account = createAllocatorAccount();
    $websiteA = createAllocatorWebsite($account, 'site-a');
    $websiteB = createAllocatorWebsite($account, 'site-b');

    attachDraftSlug($websiteA, 'about-us');

    expect($allocator->allocate($websiteB, 'About Us'))->toBe('about-us');
});

test('slug allocator uses fallback when slugification is empty', function () {
    $allocator = new PageSlugAllocator;
    $account = createAllocatorAccount();
    $website = createAllocatorWebsite($account, 'site-a');

    expect($allocator->baseFromName('!!!'))->toBe('page')
        ->and($allocator->allocate($website, '!!!'))->toBe('page');
});
