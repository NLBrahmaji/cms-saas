<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectionType extends Model
{
    public const STATUS_ACTIVE = 'active';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'key',
        'description',
        'content_schema',
        'sort_order',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content_schema' => 'array',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(SectionTemplate::class);
    }
}
