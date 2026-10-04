<?php

use App\Models\Account;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\PageSectionContent;
use App\Models\PageVersion;
use App\Models\User;
use App\Models\Website;
use App\Support\Page\PageVersionSnapshotCloner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('page_sections table has public_id column with version scoped uniqueness', function () {
    expect(Schema::hasColumn('page_sections', 'public_id'))->toBeTrue();

    $indexes = collect(Schema::getIndexes('page_sections'));

    expect($indexes->contains(fn (array $index) => $index['columns'] === ['page_version_id', 'public_id'] && $index['unique'] === true))
        ->toBeTrue();
});

test('new page section receives a unique public_id automatically', function () {
    $templateId = createSectionIdentityTemplateId();

    $version = createSectionIdentityPageVersion();

    $sectionA = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $templateId,
        'sort_order' => 0,
    ]);

    $sectionB = PageSection::query()->create([
        'page_version_id' => $version->id,
        'section_template_id' => $templateId,
        'sort_order' => 1,
    ]);

    expect($sectionA->public_id)->not->toBeEmpty()
        ->and($sectionB->public_id)->not->toBeEmpty()
        ->and($sectionA->public_id)->not->toBe($sectionB->public_id);
});

test('snapshot clone preserves public_id while assigning new numeric id', function () {
    $user = User::query()->create([
        'name' => 'Cloner',
        'email' => 'cloner-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);

    $account = Account::query()->create([
        'owner_id' => $user->id,
        'name' => 'Account',
        'status' => 'active',
    ]);

    $website = Website::query()->create([
        'account_id' => $account->id,
        'name' => 'Site',
        'subdomain' => 'site-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ]);

    $page = Page::query()->create(['website_id' => $website->id]);

    $sourceVersion = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'Home',
        'slug' => 'home',
        'created_by' => $user->id,
    ]);

    $page->assignDraftVersion($sourceVersion);

    $templateId = createSectionIdentityTemplateId();

    $sourceSection = PageSection::query()->create([
        'page_version_id' => $sourceVersion->id,
        'section_template_id' => $templateId,
        'sort_order' => 2,
        'is_visible' => true,
        'settings' => ['layout' => 'wide'],
    ]);

    PageSectionContent::query()->create([
        'page_section_id' => $sourceSection->id,
        'content' => ['title' => 'Hello'],
    ]);

    $sourcePublicId = $sourceSection->public_id;
    $sourceNumericId = $sourceSection->id;

    $cloner = app(PageVersionSnapshotCloner::class);
    $targetVersion = $cloner->cloneToNewDraftVersion($page, $sourceVersion, $user);

    $clonedSection = PageSection::query()
        ->where('page_version_id', $targetVersion->id)
        ->firstOrFail();

    $clonedContent = PageSectionContent::query()
        ->where('page_section_id', $clonedSection->id)
        ->firstOrFail();

    expect($clonedSection->id)->not->toBe($sourceNumericId)
        ->and($clonedSection->public_id)->toBe($sourcePublicId)
        ->and($clonedSection->sort_order)->toBe(2)
        ->and($clonedSection->is_visible)->toBeTrue()
        ->and($clonedSection->settings)->toBe(['layout' => 'wide'])
        ->and($clonedContent->content)->toBe(['title' => 'Hello']);

    $sourceSection->refresh();

    expect($sourceSection->public_id)->toBe($sourcePublicId)
        ->and($sourceSection->settings)->toBe(['layout' => 'wide']);
});

test('soft deleted sections are not cloned and keep their public_id on source version', function () {
    $user = User::query()->create([
        'name' => 'Cloner',
        'email' => 'cloner-deleted-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);

    $page = Page::query()->create(['website_id' => Website::query()->create([
        'account_id' => Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Account',
            'status' => 'active',
        ])->id,
        'name' => 'Site',
        'subdomain' => 'site-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ])->id]);

    $sourceVersion = PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'Home',
        'slug' => 'home',
        'created_by' => $user->id,
    ]);

    $templateId = createSectionIdentityTemplateId();

    $deleted = PageSection::query()->create([
        'page_version_id' => $sourceVersion->id,
        'section_template_id' => $templateId,
        'sort_order' => 0,
    ]);

    $deletedPublicId = $deleted->public_id;
    $deleted->delete();

    $cloner = app(PageVersionSnapshotCloner::class);
    $targetVersion = $cloner->cloneToNewDraftVersion($page, $sourceVersion, $user);

    expect(PageSection::query()->where('page_version_id', $targetVersion->id)->count())->toBe(0)
        ->and(PageSection::withTrashed()->find($deleted->id)?->public_id)->toBe($deletedPublicId);
});

function createSectionIdentityTemplateId(): int
{
    $typeId = DB::table('section_types')->insertGetId([
        'name' => 'Hero',
        'key' => 'hero-'.uniqid(),
        'content_schema' => json_encode(['type' => 'object']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (int) DB::table('section_templates')->insertGetId([
        'section_type_id' => $typeId,
        'name' => 'Hero',
        'key' => 'hero-template-'.uniqid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function createSectionIdentityPageVersion(): PageVersion
{
    $user = User::query()->create([
        'name' => 'Creator',
        'email' => 'creator-'.uniqid().'@example.com',
        'password' => Hash::make('Str0ngPass!'),
    ]);

    $page = Page::query()->create(['website_id' => Website::query()->create([
        'account_id' => Account::query()->create([
            'owner_id' => $user->id,
            'name' => 'Account',
            'status' => 'active',
        ])->id,
        'name' => 'Site',
        'subdomain' => 'site-'.uniqid(),
        'status' => 'draft',
        'timezone' => 'UTC',
    ])->id]);

    return PageVersion::query()->create([
        'page_id' => $page->id,
        'version' => 1,
        'name' => 'Home',
        'slug' => 'home',
        'created_by' => $user->id,
    ]);
}
