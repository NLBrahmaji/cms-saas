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
use App\Support\Navigation\InvalidNavigationVersionItemTreeException;
use App\Support\Navigation\NavigationEditableDraftPreparer;
use App\Support\Navigation\NavigationMissingDraftException;
use App\Support\Navigation\NavigationVersionSnapshotCloner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('navigation snapshot cloner rejects source version from another navigation', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigationA = createNavigationSnapshotNavigation($website);
    $navigationB = createNavigationSnapshotNavigation($website);

    $source = NavigationVersion::query()->create([
        'navigation_id' => $navigationA->id,
        'version' => 1,
        'created_by' => $user->id,
    ]);

    $cloner = app(NavigationVersionSnapshotCloner::class);

    expect(fn () => $cloner->cloneToNewDraftVersion($navigationB, $source, $user))
        ->toThrow(InvalidArgumentException::class, 'Source version must belong to this navigation.');
});

test('navigation snapshot cloner clones empty version', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    $source = NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => 1,
        'created_by' => $user->id,
        'published_by' => $user->id,
        'published_at' => now(),
    ]);

    $clone = app(NavigationVersionSnapshotCloner::class)
        ->cloneToNewDraftVersion($navigation, $source, $user);

    expect($clone->version)->toBe(2)
        ->and($clone->created_by)->toBe($user->id)
        ->and($clone->published_by)->toBeNull()
        ->and($clone->published_at)->toBeNull()
        ->and(NavigationItem::query()->where('navigation_version_id', $clone->id)->count())->toBe(0);
});

test('navigation snapshot cloner preserves flat items public_id sort_order and targets', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $page = createNavigationSnapshotPage($website, $user);
    $navigation = createNavigationSnapshotNavigation($website);

    $source = createNavigationSnapshotVersion($navigation, $user, 1);

    $urlItem = createNavigationSnapshotItem($source, [
        'type' => NavigationItemType::Url,
        'url' => '/contact',
        'sort_order' => 5,
        'open_in_new_tab' => true,
        'label' => 'Contact',
    ]);

    $pageItem = createNavigationSnapshotItem($source, [
        'type' => NavigationItemType::Page,
        'page_id' => $page->id,
        'url' => null,
        'sort_order' => 0,
        'label' => null,
    ]);

    $clone = app(NavigationVersionSnapshotCloner::class)
        ->cloneToNewDraftVersion($navigation, $source, $user);

    $clonedItems = NavigationItem::query()
        ->where('navigation_version_id', $clone->id)
        ->orderBy('sort_order')
        ->orderBy('id')
        ->get();

    expect($clonedItems)->toHaveCount(2)
        ->and($clonedItems[0]->public_id)->toBe($pageItem->public_id)
        ->and($clonedItems[0]->sort_order)->toBe(0)
        ->and($clonedItems[0]->page_id)->toBe($page->id)
        ->and($clonedItems[0]->url)->toBeNull()
        ->and($clonedItems[1]->public_id)->toBe($urlItem->public_id)
        ->and($clonedItems[1]->sort_order)->toBe(5)
        ->and($clonedItems[1]->url)->toBe('/contact')
        ->and($clonedItems[1]->open_in_new_tab)->toBeTrue()
        ->and($clonedItems[1]->page_id)->toBeNull();
});

test('navigation snapshot cloner remaps nested tree parent ids', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);
    $source = createNavigationSnapshotVersion($navigation, $user, 1);

    $a = createNavigationSnapshotItem($source, ['sort_order' => 0, 'url' => '/a']);
    $b = createNavigationSnapshotItem($source, ['parent_id' => $a->id, 'sort_order' => 1, 'url' => '/b']);
    $c = createNavigationSnapshotItem($source, ['parent_id' => $b->id, 'sort_order' => 2, 'url' => '/c']);
    $d = createNavigationSnapshotItem($source, ['sort_order' => 3, 'url' => '/d']);

    $clone = app(NavigationVersionSnapshotCloner::class)
        ->cloneToNewDraftVersion($navigation, $source, $user);

    $byPublicId = NavigationItem::query()
        ->where('navigation_version_id', $clone->id)
        ->get()
        ->keyBy('public_id');

    $cloneA = $byPublicId->get($a->public_id);
    $cloneB = $byPublicId->get($b->public_id);
    $cloneC = $byPublicId->get($c->public_id);
    $cloneD = $byPublicId->get($d->public_id);

    expect($cloneA->parent_id)->toBeNull()
        ->and($cloneB->parent_id)->toBe($cloneA->id)
        ->and($cloneC->parent_id)->toBe($cloneB->id)
        ->and($cloneD->parent_id)->toBeNull()
        ->and($cloneB->parent_id)->not->toBe($a->id)
        ->and($cloneC->parent_id)->not->toBe($b->id);

    assertNavigationSnapshotParentsBelongToVersion($clone->id, $byPublicId->values());
});

test('navigation snapshot cloner handles multiple roots and deep nesting', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);
    $source = createNavigationSnapshotVersion($navigation, $user, 1);

    $a = createNavigationSnapshotItem($source, ['url' => '/a', 'sort_order' => 0]);
    $b = createNavigationSnapshotItem($source, ['parent_id' => $a->id, 'url' => '/b', 'sort_order' => 1]);
    $c = createNavigationSnapshotItem($source, ['parent_id' => $b->id, 'url' => '/c', 'sort_order' => 2]);
    $d = createNavigationSnapshotItem($source, ['parent_id' => $c->id, 'url' => '/d', 'sort_order' => 3]);

    $rootTwo = createNavigationSnapshotItem($source, ['url' => '/e', 'sort_order' => 4]);
    $childTwo = createNavigationSnapshotItem($source, ['parent_id' => $rootTwo->id, 'url' => '/f', 'sort_order' => 5]);

    $clone = app(NavigationVersionSnapshotCloner::class)
        ->cloneToNewDraftVersion($navigation, $source, $user);

    $cloned = NavigationItem::query()->where('navigation_version_id', $clone->id)->get();

    expect($cloned)->toHaveCount(6);

    assertNavigationSnapshotParentsBelongToVersion($clone->id, $cloned);

    $mappedD = $cloned->firstWhere('public_id', $d->public_id);
    $mappedC = $cloned->firstWhere('public_id', $c->public_id);
    $mappedChildTwo = $cloned->firstWhere('public_id', $childTwo->public_id);

    expect($mappedD->parent_id)->toBe($mappedC->id)
        ->and($mappedChildTwo->parent->navigation_version_id)->toBe($clone->id);
});

test('navigation snapshot cloner remaps parents regardless of source row order', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);
    $source = createNavigationSnapshotVersion($navigation, $user, 1);

    $parent = NavigationItem::query()->create([
        'navigation_version_id' => $source->id,
        'type' => NavigationItemType::Url,
        'url' => '/parent',
        'sort_order' => 10,
    ]);

    $child = NavigationItem::query()->create([
        'navigation_version_id' => $source->id,
        'type' => NavigationItemType::Url,
        'url' => '/child',
        'sort_order' => 0,
        'parent_id' => $parent->id,
    ]);

    $clone = app(NavigationVersionSnapshotCloner::class)
        ->cloneToNewDraftVersion($navigation, $source, $user);

    $clonedChild = NavigationItem::query()
        ->where('navigation_version_id', $clone->id)
        ->wherePublicId($child->public_id)
        ->firstOrFail();

    $clonedParent = NavigationItem::query()
        ->where('navigation_version_id', $clone->id)
        ->wherePublicId($parent->public_id)
        ->firstOrFail();

    expect($clonedChild->parent_id)->toBe($clonedParent->id);
});

test('cloned navigation snapshots are independent rows', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);
    $source = createNavigationSnapshotVersion($navigation, $user, 1);

    $item = createNavigationSnapshotItem($source, ['url' => '/about', 'label' => 'About']);

    $clone = app(NavigationVersionSnapshotCloner::class)
        ->cloneToNewDraftVersion($navigation, $source, $user);

    $cloned = NavigationItem::query()
        ->where('navigation_version_id', $clone->id)
        ->firstOrFail();

    expect($cloned->id)->not->toBe($item->id)
        ->and($cloned->public_id)->toBe($item->public_id);

    $cloned->update(['label' => 'Changed on clone']);

    expect($item->refresh()->label)->toBe('About');

    $item->update(['label' => 'Changed on source']);

    expect($cloned->refresh()->label)->toBe('Changed on clone');
});

test('navigation snapshot cloner rejects cross version parent reference', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    $v1 = createNavigationSnapshotVersion($navigation, $user, 1);
    $v2 = createNavigationSnapshotVersion($navigation, $user, 2);

    $foreignParent = createNavigationSnapshotItem($v2, ['url' => '/foreign']);

    NavigationItem::query()->create([
        'navigation_version_id' => $v1->id,
        'type' => NavigationItemType::Url,
        'url' => '/child',
        'parent_id' => $foreignParent->id,
        'sort_order' => 0,
    ]);

    $cloner = app(NavigationVersionSnapshotCloner::class);

    expect(fn () => $cloner->cloneToNewDraftVersion($navigation, $v1, $user))
        ->toThrow(InvalidNavigationVersionItemTreeException::class);

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->max('version'))->toBe(2);
});

test('navigation snapshot cloner rejects self parent', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);
    $source = createNavigationSnapshotVersion($navigation, $user, 1);

    $item = createNavigationSnapshotItem($source, ['url' => '/solo']);
    $item->update(['parent_id' => $item->id]);

    expect(fn () => app(NavigationVersionSnapshotCloner::class)->cloneToNewDraftVersion($navigation, $source->refresh(), $user))
        ->toThrow(InvalidNavigationVersionItemTreeException::class);
});

test('navigation snapshot cloner rejects ancestor cycle', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);
    $source = createNavigationSnapshotVersion($navigation, $user, 1);

    $a = createNavigationSnapshotItem($source, ['url' => '/a']);
    $b = createNavigationSnapshotItem($source, ['url' => '/b', 'parent_id' => $a->id]);

    $a->update(['parent_id' => $b->id]);

    expect(fn () => app(NavigationVersionSnapshotCloner::class)->cloneToNewDraftVersion($navigation, $source, $user))
        ->toThrow(InvalidNavigationVersionItemTreeException::class);
});

test('navigation editable draft preparer returns unpublished v1 without cloning', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    $v1 = createNavigationSnapshotVersion($navigation, $user, 1);
    $navigation->assignDraftVersion($v1);

    $result = app(NavigationEditableDraftPreparer::class)->prepare($navigation, $user);

    expect($result->id)->toBe($v1->id)
        ->and(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and($navigation->refresh()->draft_version_id)->toBe($v1->id)
        ->and($navigation->published_version_id)->toBeNull();
});

test('navigation editable draft preparer returns ahead draft without cloning', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    $v1 = createNavigationSnapshotVersion($navigation, $user, 1);
    $v2 = createNavigationSnapshotVersion($navigation, $user, 2);

    $navigation->assignPublishedVersion($v1);
    $navigation->assignDraftVersion($v2);

    $result = app(NavigationEditableDraftPreparer::class)->prepare($navigation, $user);

    expect($result->id)->toBe($v2->id)
        ->and(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2);
});

test('navigation editable draft preparer clones canonical published snapshot', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    $v1 = createNavigationSnapshotVersion($navigation, $user, 1);
    createNavigationSnapshotItem($v1, ['url' => '/home']);

    $navigation->assignDraftVersion($v1);
    $navigation->assignPublishedVersion($v1);

    $draft = app(NavigationEditableDraftPreparer::class)->prepare($navigation, $user);

    $navigation->refresh();

    expect($navigation->published_version_id)->toBe($v1->id)
        ->and($navigation->draft_version_id)->toBe($draft->id)
        ->and($draft->version)->toBe(2)
        ->and($draft->created_by)->toBe($user->id)
        ->and(NavigationItem::query()->where('navigation_version_id', $draft->id)->count())->toBe(1);
});

test('navigation editable draft preparer uses max version plus one when historical versions exist', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    $v1 = createNavigationSnapshotVersion($navigation, $user, 1);
    createNavigationSnapshotVersion($navigation, $user, 2);
    createNavigationSnapshotVersion($navigation, $user, 3);

    $navigation->assignDraftVersion($v1);
    $navigation->assignPublishedVersion($v1);

    $draft = app(NavigationEditableDraftPreparer::class)->prepare($navigation, $user);

    expect($draft->version)->toBe(4)
        ->and($navigation->refresh()->published_version_id)->toBe($v1->id);
});

test('navigation editable draft preparer fails when draft pointer is missing', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    expect(fn () => app(NavigationEditableDraftPreparer::class)->prepare($navigation, $user))
        ->toThrow(NavigationMissingDraftException::class);
});

test('navigation editable draft preparer rolls back clone when item cloning fails', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    $v1 = createNavigationSnapshotVersion($navigation, $user, 1);
    createNavigationSnapshotItem($v1, ['url' => '/one']);
    createNavigationSnapshotItem($v1, ['url' => '/two']);

    $navigation->assignDraftVersion($v1);
    $navigation->assignPublishedVersion($v1);

    $createdCount = 0;

    NavigationItem::creating(function () use (&$createdCount): void {
        $createdCount++;

        if ($createdCount >= 2) {
            throw new RuntimeException('Simulated item clone failure');
        }
    });

    try {
        DB::transaction(function () use ($navigation, $user): void {
            app(NavigationEditableDraftPreparer::class)->prepare($navigation, $user);
        });
    } catch (RuntimeException) {
        // expected
    } finally {
        NavigationItem::flushEventListeners();
    }

    $navigation->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and(NavigationItem::query()->count())->toBe(2)
        ->and($navigation->draft_version_id)->toBe($v1->id)
        ->and($navigation->published_version_id)->toBe($v1->id);
});

test('navigation editable draft preparer rolls back clone when draft pointer assignment fails', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    $v1 = createNavigationSnapshotVersion($navigation, $user, 1);
    createNavigationSnapshotItem($v1, ['url' => '/home']);

    $navigation->assignDraftVersion($v1);
    $navigation->assignPublishedVersion($v1);

    Navigation::updating(function (): void {
        throw new RuntimeException('Simulated pointer failure');
    });

    try {
        DB::transaction(function () use ($navigation, $user): void {
            app(NavigationEditableDraftPreparer::class)->prepare($navigation, $user);
        });
    } catch (RuntimeException) {
        // expected
    } finally {
        Navigation::flushEventListeners();
    }

    $navigation->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(1)
        ->and(NavigationItem::query()->count())->toBe(1)
        ->and($navigation->draft_version_id)->toBe($v1->id)
        ->and($navigation->published_version_id)->toBe($v1->id);
});

test('navigation editable draft preparer rolls back corrupt tree clone through transaction', function () {
    $user = createNavigationSnapshotUser();
    $website = createNavigationSnapshotWebsite();
    $navigation = createNavigationSnapshotNavigation($website);

    $v1 = createNavigationSnapshotVersion($navigation, $user, 1);
    $v2 = createNavigationSnapshotVersion($navigation, $user, 2);
    $foreign = createNavigationSnapshotItem($v2, ['url' => '/foreign']);

    NavigationItem::query()->create([
        'navigation_version_id' => $v1->id,
        'type' => NavigationItemType::Url,
        'url' => '/bad',
        'parent_id' => $foreign->id,
        'sort_order' => 0,
    ]);

    $navigation->assignDraftVersion($v1);
    $navigation->assignPublishedVersion($v1);

    try {
        DB::transaction(function () use ($navigation, $user): void {
            app(NavigationEditableDraftPreparer::class)->prepare($navigation, $user);
        });
    } catch (InvalidNavigationVersionItemTreeException) {
        // expected
    }

    $navigation->refresh();

    expect(NavigationVersion::query()->where('navigation_id', $navigation->id)->count())->toBe(2)
        ->and($navigation->draft_version_id)->toBe($v1->id)
        ->and($navigation->published_version_id)->toBe($v1->id);
});

function createNavigationSnapshotUser(): User
{
    return User::query()->create([
        'name' => 'Snapshot User',
        'email' => 'snapshot-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);
}

function createNavigationSnapshotWebsite(): Website
{
    $user = createNavigationSnapshotUser();

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

function createNavigationSnapshotNavigation(Website $website): Navigation
{
    return Navigation::query()->create([
        'website_id' => $website->id,
        'name' => 'Menu',
        'key' => 'menu-'.uniqid(),
    ]);
}

function createNavigationSnapshotVersion(Navigation $navigation, User $user, int $version): NavigationVersion
{
    return NavigationVersion::query()->create([
        'navigation_id' => $navigation->id,
        'version' => $version,
        'created_by' => $user->id,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function createNavigationSnapshotItem(NavigationVersion $version, array $overrides = []): NavigationItem
{
    return NavigationItem::query()->create(array_merge([
        'navigation_version_id' => $version->id,
        'type' => NavigationItemType::Url,
        'url' => '/item-'.uniqid(),
        'sort_order' => 0,
    ], $overrides));
}

function createNavigationSnapshotPage(Website $website, User $user): Page
{
    $page = Page::query()->create(['website_id' => $website->id]);

    $version = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'Page',
        'slug' => 'page',
        'created_by' => $user->id,
    ]);

    $page->assignDraftVersion($version);

    return $page->refresh();
}

/**
 * @param  iterable<int, NavigationItem>  $items
 */
function assertNavigationSnapshotParentsBelongToVersion(int $versionId, iterable $items): void
{
    foreach ($items as $item) {
        if ($item->parent_id === null) {
            continue;
        }

        $parent = NavigationItem::query()->find($item->parent_id);

        expect($parent)->not->toBeNull()
            ->and($parent->navigation_version_id)->toBe($versionId);
    }
}
