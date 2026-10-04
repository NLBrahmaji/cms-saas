<?php

namespace App\Support\Section;

use App\Models\SectionTemplate;
use App\Models\SectionType;

class ActiveSectionTemplateResolver
{
    public function findActive(int $sectionTemplateId): ?SectionTemplate
    {
        $template = SectionTemplate::query()
            ->with('sectionType')
            ->find($sectionTemplateId);

        if ($template === null || $template->status !== SectionTemplate::STATUS_ACTIVE) {
            return null;
        }

        $type = $template->sectionType;

        if ($type === null || $type->status !== SectionType::STATUS_ACTIVE) {
            return null;
        }

        return $template;
    }
}
