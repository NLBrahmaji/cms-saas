<?php

namespace App\Http\Resources\Website;

use App\Models\WebsiteSeoSetting;
use App\Support\Website\WebsiteSeoEffectiveState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteSeoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof WebsiteSeoSetting) {
            $state = WebsiteSeoEffectiveState::empty();
        } else {
            $state = WebsiteSeoEffectiveState::fromSeoRow($this->resource);
        }

        return [
            'title_suffix' => $state['title_suffix'],
            'default_description' => $state['default_description'],
            'default_og_image_id' => $state['default_og_image_id'],
            'robots_index' => $state['robots_index'],
            'robots_follow' => $state['robots_follow'],
        ];
    }
}
