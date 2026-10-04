<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionTemplate extends Model
{
    public const STATUS_ACTIVE = 'active';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'section_type_id',
        'name',
        'key',
        'settings_schema',
        'sort_order',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings_schema' => 'array',
        ];
    }

    public function sectionType(): BelongsTo
    {
        return $this->belongsTo(SectionType::class);
    }
}
