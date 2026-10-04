<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PageSection extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'page_version_id',
        'section_template_id',
        'sort_order',
        'is_visible',
        'settings',
    ];

    protected static function booted(): void
    {
        static::creating(function (PageSection $section): void {
            if ($section->public_id === null || $section->public_id === '') {
                $section->public_id = (string) Str::uuid();
            }
        });
    }

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

    public function template(): BelongsTo
    {
        return $this->belongsTo(SectionTemplate::class, 'section_template_id');
    }

    public function content(): HasOne
    {
        return $this->hasOne(PageSectionContent::class);
    }

    /**
     * @param  Builder<PageSection>  $query
     * @return Builder<PageSection>
     */
    public function scopeWherePublicId(Builder $query, string $publicId): Builder
    {
        return $query->where('public_id', $publicId);
    }
}
