<?php

namespace App\Http\Resources\Website;

use App\Models\WebsiteBranding;
use App\Support\Website\WebsiteBrandingEffectiveState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteBrandingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof WebsiteBranding) {
            $state = WebsiteBrandingEffectiveState::empty();
        } else {
            $state = WebsiteBrandingEffectiveState::fromBrandingRow($this->resource);
        }

        return [
            'logo_media_id' => $state['logo_media_id'],
            'logo_light_media_id' => $state['logo_light_media_id'],
            'logo_dark_media_id' => $state['logo_dark_media_id'],
            'favicon_media_id' => $state['favicon_media_id'],
        ];
    }
}
