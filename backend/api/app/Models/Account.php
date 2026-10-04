<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(AccountMember::class);
    }

    /**
     * @param  Builder<Account>  $query
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereHas('members', function (Builder $members) use ($user): void {
                $members
                    ->where('user_id', $user->id)
                    ->where('status', 'active');
            });
    }
}
