<?php

namespace App\Support\Navigation;

use App\Models\Navigation;
use Illuminate\Support\Facades\DB;

class NavigationMetadataUpdater
{
    /**
     * @param  array<string, string>  $changes
     */
    public function update(Navigation $navigation, array $changes): Navigation
    {
        if ($changes === []) {
            return $navigation;
        }

        return DB::transaction(function () use ($navigation, $changes): Navigation {
            $lockedNavigation = Navigation::query()
                ->whereKey($navigation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $payload = [];

            if (array_key_exists('name', $changes) && $changes['name'] !== $lockedNavigation->name) {
                $payload['name'] = $changes['name'];
            }

            if (array_key_exists('key', $changes) && $changes['key'] !== $lockedNavigation->key) {
                $payload['key'] = $changes['key'];
            }

            if ($payload === []) {
                return $lockedNavigation;
            }

            $lockedNavigation->update($payload);

            return $lockedNavigation->refresh();
        });
    }
}
