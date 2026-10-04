<?php

namespace App\Support\Navigation;

use Illuminate\Validation\ValidationException;

class InvalidNavigationItemParentException
{
    public static function throw(): never
    {
        throw ValidationException::withMessages([
            'parent_id' => ['The selected parent item is invalid.'],
        ]);
    }
}
