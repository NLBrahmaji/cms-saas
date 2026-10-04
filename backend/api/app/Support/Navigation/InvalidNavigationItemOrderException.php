<?php

namespace App\Support\Navigation;

use Illuminate\Validation\ValidationException;

class InvalidNavigationItemOrderException
{
    public static function throw(): never
    {
        throw ValidationException::withMessages([
            'item_ids' => ['The item ids list is invalid for the selected sibling collection.'],
        ]);
    }
}
