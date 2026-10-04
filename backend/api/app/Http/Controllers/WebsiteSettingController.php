<?php

namespace App\Http\Controllers;

use App\Http\Requests\Website\UpdateWebsiteSettingsRequest;
use App\Http\Resources\Website\WebsiteSettingResource;
use App\Models\Account;
use App\Models\Website;
use App\Models\WebsiteSetting;

class WebsiteSettingController extends Controller
{
    public function show(Account $account, Website $website): WebsiteSettingResource
    {
        $this->authorize('view', $website);

        $settings = $this->resolveSettings($website);

        if ($settings === null) {
            abort(404);
        }

        return new WebsiteSettingResource($settings);
    }

    public function update(UpdateWebsiteSettingsRequest $request, Account $account, Website $website): WebsiteSettingResource
    {
        $settings = $this->resolveSettings($website);

        if ($settings === null) {
            abort(404);
        }

        $request->applyTo($settings);
        $settings->save();

        return new WebsiteSettingResource($settings);
    }

    private function resolveSettings(Website $website): ?WebsiteSetting
    {
        return $website->settings;
    }
}
