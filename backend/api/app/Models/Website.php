<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'home_page_id',
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

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function homePage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'home_page_id');
    }

    public function assignHomePage(?Page $page): void
    {
        if ($page === null) {
            $this->home_page_id = null;
            $this->save();

            return;
        }

        if ($page->trashed()) {
            throw new \InvalidArgumentException('Home page must not be soft-deleted.');
        }

        if ((int) $page->website_id !== (int) $this->id) {
            throw new \InvalidArgumentException('Home page must belong to this website.');
        }

        $this->home_page_id = $page->id;
        $this->save();
    }
}
