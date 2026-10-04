<?php

namespace App\Models;

use App\Navigation\NavigationItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NavigationItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'navigation_version_id',
        'parent_id',
        'type',
        'page_id',
        'label',
        'url',
        'sort_order',
        'open_in_new_tab',
    ];

    protected static function booted(): void
    {
        static::creating(function (NavigationItem $item): void {
            if ($item->public_id === null || $item->public_id === '') {
                $item->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NavigationItemType::class,
            'sort_order' => 'integer',
            'open_in_new_tab' => 'boolean',
        ];
    }

    public function navigationVersion(): BelongsTo
    {
        return $this->belongsTo(NavigationVersion::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(NavigationItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(NavigationItem::class, 'parent_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @param  Builder<NavigationItem>  $query
     * @return Builder<NavigationItem>
     */
    public function scopeWherePublicId(Builder $query, string $publicId): Builder
    {
        return $query->where('public_id', $publicId);
    }
}
