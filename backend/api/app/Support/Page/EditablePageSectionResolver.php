<?php

namespace App\Support\Page;

use App\Models\PageSection;
use App\Models\PageVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class EditablePageSectionResolver
{
    public function resolve(PageVersion $draft, string $publicId): PageSection
    {
        if (! Str::isUuid($publicId)) {
            throw (new ModelNotFoundException)->setModel(PageSection::class);
        }

        $section = PageSection::query()
            ->where('page_version_id', $draft->id)
            ->wherePublicId($publicId)
            ->with(['template.sectionType', 'content'])
            ->first();

        if ($section === null) {
            throw (new ModelNotFoundException)->setModel(PageSection::class);
        }

        return $section;
    }
}
