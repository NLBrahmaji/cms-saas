<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function create(User $user): bool
    {
        return $user->can('media.upload');
    }

    public function viewAny(User $user): bool
    {
        return $user->can('media.view');
    }

    public function view(User $user, Media $media): bool
    {
        return $user->can('media.view');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->can('media.update');
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->can('media.delete');
    }
}
