<?php

namespace App\Support\Navigation;

use App\Models\NavigationItem;
use App\Models\NavigationVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class EditableNavigationItemResolver
{
    public function resolve(NavigationVersion $draft, string $publicId): NavigationItem
    {
        if (! Str::isUuid($publicId)) {
            throw (new ModelNotFoundException)->setModel(NavigationItem::class);
        }

        $item = NavigationItem::query()
            ->where('navigation_version_id', $draft->id)
            ->wherePublicId($publicId)
            ->first();

        if ($item === null) {
            throw (new ModelNotFoundException)->setModel(NavigationItem::class);
        }

        return $item;
    }
}
