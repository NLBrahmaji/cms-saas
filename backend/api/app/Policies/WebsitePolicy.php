<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;

class WebsitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('website.view');
    }

    public function view(User $user, Website $website): bool
    {
        return $user->can('website.view');
    }

    public function create(User $user): bool
    {
        return $user->can('website.create');
    }

    public function update(User $user, Website $website): bool
    {
        return $user->can('website.update');
    }

    public function delete(User $user, Website $website): bool
    {
        return $user->can('website.delete');
    }
}
