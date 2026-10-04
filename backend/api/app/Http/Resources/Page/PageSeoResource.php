<?php

namespace App\Http\Resources\Page;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageSeoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $state */
        $state = $this->resource;

        return [
            'meta_title' => $state['meta_title'] ?? null,
            'meta_description' => $state['meta_description'] ?? null,
            'og_title' => $state['og_title'] ?? null,
            'og_description' => $state['og_description'] ?? null,
            'og_image_id' => $state['og_image_id'] ?? null,
            'canonical_url' => $state['canonical_url'] ?? null,
            'robots_index' => $state['robots_index'] ?? null,
            'robots_follow' => $state['robots_follow'] ?? null,
        ];
    }
}
