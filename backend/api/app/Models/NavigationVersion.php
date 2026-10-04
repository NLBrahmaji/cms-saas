<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationVersion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'navigation_id',
        'version',
        'created_by',
        'published_by',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function navigation(): BelongsTo
    {
        return $this->belongsTo(Navigation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(NavigationItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
