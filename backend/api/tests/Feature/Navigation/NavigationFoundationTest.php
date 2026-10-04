<?php

use App\Models\Account;
use App\Models\Navigation;
use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use App\Models\Page;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\Website;
use App\Navigation\NavigationItemType;
use App\Navigation\NavigationItemUrlContract;
use Database\Seeders\AccountPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('navigation_items table has public_id with version scoped uniqueness', function () {
    expect(Schema::hasColumn('navigation_items', 'public_id'))->toBeTrue();

    $indexes = collect(Schema::getIndexes('navigation_items'));

    expect($indexes->contains(fn (array $index) => $index['columns'] === ['navigation_version_id', 'public_id'] && $index['unique'] === true))
        ->toBeTrue();
});

test('account permission seeder includes dedicated navigation permissions', function () {
    $this->seed(AccountPermissionSeeder::class);

    $expected = [
        'navigation.view',
        'navigation.create',
        'navigation.update',
        'navigation.delete',
        'navigation.publish',
    ];

    foreach ($expected as $permission) {
        expect(Permission::query()->where('name', $permission)->exists())->toBeTrue();
    }

    expect(AccountPermissionSeeder::PERMISSIONS)->toContain(...$expected);
});

test('navigation model relationships and pointer helpers', function () {
    $website = createNavigationFoundationWebsite();
    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Main Menu',
        'key' => 'slot-'.uniqid(),
    ]);

    $version = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
    ]);

    $navigation->assignDraftVersion($version);
    $navigation->assignPublishedVersion($version);

    $navigation->refresh()->load(['website', 'versions', 'draftVersion', 'publishedVersion']);

    expect($navigation->website->id)->toBe($website->id)
        ->and($navigation->versions)->toHaveCount(1)
        ->and($navigation->draftVersion->id)->toBe($version->id)
        ->and($navigation->publishedVersion->id)->toBe($version->id);

    $otherNavigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Other',
        'key' => 'other-'.uniqid(),
    ]);

    $foreignVersion = NavigationVersion::query()->create([
        'navigation_id' => $otherNavigation->id,
        'version' => 1,
    ]);

    expect(fn () => $navigation->assignDraftVersion($foreignVersion))
        ->toThrow(InvalidArgumentException::class, 'Draft version must belong to this navigation.');

    expect(fn () => $navigation->assignPublishedVersion($foreignVersion))
        ->toThrow(InvalidArgumentException::class, 'Published version must belong to this navigation.');
});

test('website has many navigations', function () {
    $website = createNavigationFoundationWebsite();

    $first = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'A',
        'key' => 'a-'.uniqid(),
    ]);

    $second = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'B',
        'key' => 'b-'.uniqid(),
    ]);

    $website->load('navigations');

    expect($website->navigations->pluck('id')->sort()->values()->all())
        ->toEqual(collect([$first->id, $second->id])->sort()->values()->all());
});

test('navigation version relationships and published_at cast', function () {
    $user = createNavigationFoundationUser();
    $website = createNavigationFoundationWebsite();
    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);

    $publishedAt = now()->startOfSecond();

    $version = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
        'created_by' => $user->id,
        'published_by' => $user->id,
        'published_at' => $publishedAt,
    ]);

    $item = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Page,
        'page_id' => createNavigationFoundationPage($website, $user)->id,
        'sort_order' => 0,
    ]);

    $version->refresh()->load(['navigation', 'items', 'createdBy', 'publishedBy']);

    expect($version->navigation->id)->toBe($navigation->id)
        ->and($version->items->pluck('id')->all())->toBe([$item->id])
        ->and($version->createdBy->id)->toBe($user->id)
        ->and($version->publishedBy->id)->toBe($user->id)
        ->and($version->published_at?->toDateTimeString())->toBe($publishedAt->toDateTimeString());
});

test('navigation item relationships parent children and page', function () {
    $user = createNavigationFoundationUser();
    $website = createNavigationFoundationWebsite();
    $page = createNavigationFoundationPage($website, $user);
    $version = createNavigationFoundationVersion($website);

    $parent = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'sort_order' => 0,
    ]);

    $child = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'parent_id' => $parent->id,
        'type' => NavigationItemType::Url,
        'url' => '/contact',
        'sort_order' => 1,
        'open_in_new_tab' => true,
    ]);

    $child->refresh()->load(['navigationVersion', 'parent', 'page']);

    expect($child->navigationVersion->id)->toBe($version->id)
        ->and($child->parent->id)->toBe($parent->id)
        ->and($child->open_in_new_tab)->toBeTrue()
        ->and($parent->children->pluck('id')->all())->toBe([$child->id]);

    $pageItem = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'sort_order' => 2,
    ]);

    $pageItem->load('page');

    expect($pageItem->page->id)->toBe($page->id);
});

test('new navigation item receives automatic uuid public_id', function () {
    $version = createNavigationFoundationVersion(createNavigationFoundationWebsite());

    $item = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/about',
        'sort_order' => 0,
    ]);

    expect(Str::isUuid($item->public_id))->toBeTrue();
});

test('navigation item preserves explicitly supplied public_id', function () {
    $version = createNavigationFoundationVersion(createNavigationFoundationWebsite());
    $publicId = (string) Str::uuid();

    $item = NavigationItem::query()->create([
        'public_id' => $publicId,
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/team',
        'sort_order' => 0,
    ]);

    expect($item->public_id)->toBe($publicId);
});

test('same public_id is allowed across different navigation versions', function () {
    $website = createNavigationFoundationWebsite();
    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);

    $versionOne = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
    ]);

    $versionTwo = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 2,
    ]);

    $sharedPublicId = (string) Str::uuid();

    $first = NavigationItem::query()->create([
        'public_id' => $sharedPublicId,
        'navigation_version_id' => $versionOne->id,
        'type' => NavigationItemType::Url,
        'url' => '/v1',
        'sort_order' => 0,
    ]);

    $second = NavigationItem::query()->create([
        'public_id' => $sharedPublicId,
        'navigation_version_id' => $versionTwo->id,
        'type' => NavigationItemType::Url,
        'url' => '/v2',
        'sort_order' => 0,
    ]);

    expect($first->id)->not->toBe($second->id)
        ->and($first->public_id)->toBe($sharedPublicId)
        ->and($second->public_id)->toBe($sharedPublicId);
});

test('duplicate public_id within the same navigation version violates uniqueness', function () {
    $version = createNavigationFoundationVersion(createNavigationFoundationWebsite());
    $publicId = (string) Str::uuid();

    NavigationItem::query()->create([
        'public_id' => $publicId,
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/one',
        'sort_order' => 0,
    ]);

    expect(fn () => NavigationItem::query()->create([
        'public_id' => $publicId,
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/two',
        'sort_order' => 1,
    ]))->toThrow(QueryException::class);
});

test('navigation key is unique per website but not globally', function () {
    $websiteA = createNavigationFoundationWebsite();
    $websiteB = createNavigationFoundationWebsite();
    $key = 'shared-key-'.uniqid();

    Navigation::query()->create([
        'website_id' => $websiteA->id,
        'name' => 'A',
        'key' => $key,
    ]);

    Navigation::query()->create([
        'website_id' => $websiteB->id,
        'name' => 'B',
        'key' => $key,
    ]);

    expect(fn () => Navigation::query()->create([
        'website_id' => $websiteA->id,
        'name' => 'Duplicate',
        'key' => $key,
    ]))->toThrow(QueryException::class);
});

test('deleting a parent navigation item nulls child parent_id per database fk', function () {
    $version = createNavigationFoundationVersion(createNavigationFoundationWebsite());

    $parent = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/parent',
        'sort_order' => 0,
    ]);

    $child = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'parent_id' => $parent->id,
        'type' => NavigationItemType::Url,
        'url' => '/child',
        'sort_order' => 1,
    ]);

    $parent->delete();

    expect($child->refresh()->parent_id)->toBeNull();
});

test('page linked navigation item does not auto persist page name into label', function () {
    $user = createNavigationFoundationUser();
    $website = createNavigationFoundationWebsite();
    $page = createNavigationFoundationPage($website, $user, 'About Us', 'about-us');
    $version = createNavigationFoundationVersion($website);

    $item = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'sort_order' => 0,
    ]);

    expect($item->label)->toBeNull()
        ->and($page->draftVersion->name)->toBe('About Us');
});

test('navigation item type contract defines page and url field requirements', function () {
    expect(NavigationItemType::supported())->toEqual([
        NavigationItemType::Page,
        NavigationItemType::Url,
    ]);

    expect(NavigationItemType::Page->intendedFieldRequirements())->toBe([
        'page_id' => 'required',
        'url' => 'null',
    ]);

    expect(NavigationItemType::Url->intendedFieldRequirements())->toBe([
        'page_id' => 'null',
        'url' => 'required',
    ]);
});

test('navigation item url contract documents future allowed and forbidden targets', function () {
    expect(NavigationItemUrlContract::matchesIntendedFutureShape('/contact'))->toBeTrue()
        ->and(NavigationItemUrlContract::matchesIntendedFutureShape('https://example.com/path'))->toBeTrue()
        ->and(NavigationItemUrlContract::matchesIntendedFutureShape('http://example.com'))->toBeTrue()
        ->and(NavigationItemUrlContract::hasForbiddenScheme('javascript:alert(1)'))->toBeTrue()
        ->and(NavigationItemUrlContract::hasForbiddenScheme('data:text/html,hello'))->toBeTrue()
        ->and(NavigationItemUrlContract::hasForbiddenScheme('file:///etc/passwd'))->toBeTrue()
        ->and(NavigationItemUrlContract::matchesIntendedFutureShape('javascript:alert(1)'))->toBeFalse();
});

test('sibling ordering uses sort_order then id and allows gaps', function () {
    $version = createNavigationFoundationVersion(createNavigationFoundationWebsite());

    $second = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/second',
        'sort_order' => 10,
    ]);

    $first = NavigationItem::query()->create([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/first',
        'sort_order' => 0,
    ]);

    $ordered = NavigationItem::query()
        ->where('navigation_version_id', $version->id)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($ordered)->toBe([$first->id, $second->id]);
});

test('database allows cross navigation version parent reference until application services enforce integrity', function () {
    $website = createNavigationFoundationWebsite();
    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);

    $versionOne = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
    ]);

    $versionTwo = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 2,
    ]);

    $parent = NavigationItem::query()->create([
        'navigation_version_id' => $versionOne->id,
        'type' => NavigationItemType::Url,
        'url' => '/parent',
        'sort_order' => 0,
    ]);

    $invalidChild = NavigationItem::query()->create([
        'navigation_version_id' => $versionTwo->id,
        'parent_id' => $parent->id,
        'type' => NavigationItemType::Url,
        'url' => '/child',
        'sort_order' => 0,
    ]);

    expect($invalidChild->parent_id)->toBe($parent->id)
        ->and($invalidChild->navigation_version_id)->not->toBe($parent->navigation_version_id);
});

function createNavigationFoundationUser(): User
{
    return User::query()->create([
        'name' => 'Nav User',
        'email' => 'nav-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function createNavigationFoundationWebsite(): Website
{
    $user = createNavigationFoundationUser();

    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => 'Account',
        'status' => 'active',
    ]);

    return Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site',
        'subdomain' => 'site-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);
}

function createNavigationFoundationPage(Website $website, User $user, string $name = 'Page', string $slug = 'page'): Page
{
    $page = Page::query()->create(['website_id' => $website->id]);

    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => $name,
        'slug' => $slug,
        'created_by' => $user->id,
    ]);

    $page->assignDraftVersion($version);

    return $page->refresh();
}

function createNavigationFoundationVersion(Website $website): NavigationVersion
{
    $navigation = Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);

    return NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
    ]);
}
