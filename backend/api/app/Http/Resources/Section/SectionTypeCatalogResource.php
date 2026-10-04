<?php

namespace App\Http\Resources\Section;

use App\Models\SectionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SectionType */
class SectionTypeCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'templates' => SectionTemplateCatalogResource::collection($this->whenLoaded('templates')),
        ];
    }
}
