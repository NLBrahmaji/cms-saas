<?php

namespace App\Support\Website;

use App\Models\Website;
use App\Models\WebsiteBranding;
use Illuminate\Support\Facades\DB;

class WebsiteBrandingUpdater
{
    /**
     * @param  array<string, int|null>  $changes
     */
    public function update(Website $website, array $changes): ?WebsiteBranding
    {
        return DB::transaction(function () use ($website, $changes): ?WebsiteBranding {
            $lockedWebsite = Website::query()
                ->whereKey($website->id)
                ->lockForUpdate()
                ->firstOrFail();

            $branding = WebsiteBranding::query()
                ->where('website_id', $lockedWebsite->id)
                ->first();

            $effective = WebsiteBrandingEffectiveState::fromBrandingRow($branding);
            $candidate = WebsiteBrandingEffectiveState::applyChanges($effective, $changes);

            if (WebsiteBrandingEffectiveState::equals($effective, $candidate)) {
                return $branding;
            }

            if ($branding === null) {
                return WebsiteBranding::query()->create([
                    'website_id' => $lockedWebsite->id,
                    'logo_media_id' => $candidate['logo_media_id'],
                    'logo_light_media_id' => $candidate['logo_light_media_id'],
                    'logo_dark_media_id' => $candidate['logo_dark_media_id'],
                    'favicon_media_id' => $candidate['favicon_media_id'],
                    'theme' => null,
                ]);
            }

            foreach ($changes as $field => $value) {
                $branding->{$field} = $value;
            }

            $branding->save();

            return $branding->refresh();
        });
    }
}
