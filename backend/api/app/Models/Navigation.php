<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Navigation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'website_id',
        'name',
        'key',
        'draft_version_id',
        'published_version_id',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NavigationVersion::class);
    }

    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(NavigationVersion::class, 'draft_version_id');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(NavigationVersion::class, 'published_version_id');
    }

    public function assignDraftVersion(NavigationVersion $version): void
    {
        if ((int) $version->navigation_id !== (int) $this->id) {
            throw new \InvalidArgumentException('Draft version must belong to this navigation.');
        }

        $this->draft_version_id = $version->id;
        $this->save();
    }

    public function assignPublishedVersion(NavigationVersion $version): void
    {
        if ((int) $version->navigation_id !== (int) $this->id) {
            throw new \InvalidArgumentException('Published version must belong to this navigation.');
        }

        $this->published_version_id = $version->id;
        $this->save();
    }
}
