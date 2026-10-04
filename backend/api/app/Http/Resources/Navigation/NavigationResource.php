<?php

namespace App\Http\Resources\Navigation;

use App\Models\Navigation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Navigation */
class NavigationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->key,
            'has_published_version' => $this->published_version_id !== null,
        ];
    }
}
