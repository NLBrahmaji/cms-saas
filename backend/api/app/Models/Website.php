<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Website extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'account_id',
        'name',
        'subdomain',
        'status',
        'timezone',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(WebsiteSetting::class);
    }
}
