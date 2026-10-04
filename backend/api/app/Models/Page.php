<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Page extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'website_id',
        'draft_version_id',
        'published_version_id',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class);
    }

    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'draft_version_id');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'published_version_id');
    }

    public function assignDraftVersion(PageVersion $version): void
    {
        if ((int) $version->page_id !== (int) $this->id) {
            throw new \InvalidArgumentException('Draft version must belong to this page.');
        }

        $this->draft_version_id = $version->id;
        $this->save();
    }
}
