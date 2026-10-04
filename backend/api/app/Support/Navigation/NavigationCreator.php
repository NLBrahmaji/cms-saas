<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use App\Models\NavigationVersion;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class NavigationCreator
{
    public function create(Website $website, User $user, string $name, string $key): Navigation
    {
        return DB::transaction(function () use ($website, $user, $name, $key): Navigation {
            $navigation = Navigation::query()->create([
                'website_id' => $website->id,
                'name' => $name,
                'key' => $key,
            ]);

            $version = NavigationVersion::query()->create([
                'navigation_id' => $navigation->id,
                'version' => 1,
                'created_by' => $user->id,
                'published_by' => null,
                'published_at' => null,
            ]);

            $navigation->assignDraftVersion($version);

            return $navigation->refresh();
        });
    }
}
