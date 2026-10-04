<?php

namespace App\Http\Resources\Page;

use App\Models\PageSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PageSection */
class PageSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'template' => new PageSectionTemplateResource($this->whenLoaded('template')),
            'sort_order' => $this->sort_order,
            'is_visible' => $this->is_visible,
            'settings' => $this->normalizeJsonDocument($this->settings),
            'content' => $this->normalizeJsonDocument($this->content?->content),
        ];
    }

    /**
     * @return array<string, mixed>|\stdClass
     */
    private function normalizeJsonDocument(mixed $value): array|\stdClass
    {
        if ($value === null || $value === []) {
            return new \stdClass;
        }

        if (! is_array($value)) {
            return new \stdClass;
        }

        return $value;
    }
}
