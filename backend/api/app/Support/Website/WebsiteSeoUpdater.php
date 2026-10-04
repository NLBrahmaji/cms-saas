<?php

namespace App\Support\Website;

use App\Models\Website;
use App\Models\WebsiteSeoSetting;
use Illuminate\Support\Facades\DB;

class WebsiteSeoUpdater
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(Website $website, array $changes): ?WebsiteSeoSetting
    {
        return DB::transaction(function () use ($website, $changes): ?WebsiteSeoSetting {
            $lockedWebsite = Website::query()
                ->whereKey($website->id)
                ->lockForUpdate()
                ->firstOrFail();

            $seo = WebsiteSeoSetting::query()
                ->where('website_id', $lockedWebsite->id)
                ->first();

            $effective = WebsiteSeoEffectiveState::fromSeoRow($seo);
            $candidate = WebsiteSeoEffectiveState::applyChanges($effective, $changes);

            if (WebsiteSeoEffectiveState::equals($effective, $candidate)) {
                return $seo;
            }

            if ($seo === null) {
                return WebsiteSeoSetting::query()->create([
                    'website_id' => $lockedWebsite->id,
                    'title_suffix' => $candidate['title_suffix'],
                    'default_description' => $candidate['default_description'],
                    'default_og_image_id' => $candidate['default_og_image_id'],
                    'robots_index' => $candidate['robots_index'],
                    'robots_follow' => $candidate['robots_follow'],
                ]);
            }

            foreach ($changes as $field => $value) {
                $seo->{$field} = $value;
            }

            $seo->save();

            return $seo->refresh();
        });
    }
}
