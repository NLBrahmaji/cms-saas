<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PageDraftMetadataUpdater
{
    public function __construct(
        private readonly PageEditableDraftPreparer $draftPreparer,
    ) {}

    /**
     * @param  array{name?: string, slug?: string}  $changes
     */
    public function update(Page $page, User $user, array $changes): Page
    {
        if ($changes === []) {
            return $page->loadMissing('draftVersion');
        }

        return DB::transaction(function () use ($page, $user, $changes): Page {
            $lockedPage = Page::query()
                ->whereKey($page->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft = $this->draftPreparer->prepare($lockedPage, $user);

            if (array_key_exists('name', $changes)) {
                $draft->name = $changes['name'];
            }

            if (array_key_exists('slug', $changes)) {
                $draft->slug = $changes['slug'];
            }

            $draft->save();

            return $lockedPage->refresh()->load('draftVersion');
        });
    }
}
