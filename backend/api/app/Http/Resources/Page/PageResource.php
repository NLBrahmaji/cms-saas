<?php

namespace App\Http\Resources\Page;

use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Page */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $draft = $this->draftVersion;

        if ($draft === null) {
            throw new \RuntimeException('Page is missing its draft version.');
        }

        return [
            'id' => $this->id,
            'name' => $draft->name,
            'slug' => $draft->slug,
            'is_home' => $draft->is_home,
            'has_published_version' => $this->published_version_id !== null,
        ];
    }
}
