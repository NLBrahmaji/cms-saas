<?php

namespace App\Http\Controllers;

use App\Http\Resources\Section\SectionTypeCatalogResource;
use App\Models\SectionTemplate;
use App\Models\SectionType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SectionTemplateCatalogController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $types = SectionType::query()
            ->where('status', SectionType::STATUS_ACTIVE)
            ->with([
                'templates' => function ($query): void {
                    $query
                        ->where('status', SectionTemplate::STATUS_ACTIVE)
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (SectionType $type) => $type->templates->isNotEmpty())
            ->values();

        return SectionTypeCatalogResource::collection($types);
    }
}
