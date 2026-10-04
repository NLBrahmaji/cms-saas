<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PageSection extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'page_version_id',
        'section_template_id',
        'sort_order',
        'is_visible',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function pageVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'page_version_id');
    }

    public function content(): HasOne
    {
        return $this->hasOne(PageSectionContent::class);
    }
}
