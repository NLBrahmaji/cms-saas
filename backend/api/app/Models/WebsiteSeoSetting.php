<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSeoSetting extends Model
{
    protected $table = 'website_seo_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'website_id',
        'title_suffix',
        'default_description',
        'default_og_image_id',
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

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function defaultOgImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'default_og_image_id');
    }
}
