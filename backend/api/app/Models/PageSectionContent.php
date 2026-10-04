<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSectionContent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'page_section_id',
        'content',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function pageSection(): BelongsTo
    {
        return $this->belongsTo(PageSection::class);
    }
}
