<?php

namespace App\Http\Resources\Page;

use App\Models\SectionTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SectionTemplate */
class PageSectionTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->sectionType;

        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'type_key' => $type?->key,
        ];
    }
}
