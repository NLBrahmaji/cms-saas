<?php

namespace App\Policies;

use App\Models\Navigation;
use App\Models\User;

class NavigationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('navigation.view');
    }

    public function view(User $user, Navigation $navigation): bool
    {
        return $user->can('navigation.view');
    }

    public function create(User $user): bool
    {
        return $user->can('navigation.create');
    }

    public function update(User $user, Navigation $navigation): bool
    {
        return $user->can('navigation.update');
    }

    public function delete(User $user, Navigation $navigation): bool
    {
        return $user->can('navigation.delete');
    }

    public function publish(User $user, Navigation $navigation): bool
    {
        return $user->can('navigation.publish');
    }
}
