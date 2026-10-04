<?php

namespace App\Support\Navigation;

use Illuminate\Validation\ValidationException;

class InvalidNavigationPublishSnapshotException
{
    public static function throw(string $message): never
    {
        throw ValidationException::withMessages([
            'navigation' => [$message],
        ]);
    }
}
