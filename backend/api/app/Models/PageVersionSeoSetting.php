<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageVersionSeoSetting extends Model
{
    protected $table = 'page_version_seo_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'page_version_id',
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
        'og_image_id',
        'canonical_url',
        'robots_index',
        'robots_follow',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    public function pageVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'page_version_id');
    }
}
