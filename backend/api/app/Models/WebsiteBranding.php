<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteBranding extends Model
{
    protected $table = 'website_branding';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'website_id',
        'logo_media_id',
        'logo_light_media_id',
        'logo_dark_media_id',
        'favicon_media_id',
        'theme',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function logoLightMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_light_media_id');
    }

    public function logoDarkMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_dark_media_id');
    }

    public function faviconMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'favicon_media_id');
    }
}
