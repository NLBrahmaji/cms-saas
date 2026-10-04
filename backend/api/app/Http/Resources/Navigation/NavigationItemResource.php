<?php

namespace App\Http\Resources\Navigation;

use App\Models\NavigationItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NavigationItem */
class NavigationItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'type' => $this->type->value,
            'label' => $this->label,
            'page_id' => $this->page_id,
            'url' => $this->url,
            'parent_id' => $this->parent?->public_id,
            'sort_order' => $this->sort_order,
            'open_in_new_tab' => $this->open_in_new_tab,
        ];
    }
}
