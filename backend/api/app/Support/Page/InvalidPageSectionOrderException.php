<?php

namespace App\Support\Page;

use Illuminate\Validation\ValidationException;

class InvalidPageSectionOrderException
{
    /**
     * @throws ValidationException
     */
    public static function throw(): void
    {
        throw ValidationException::withMessages([
            'section_ids' => ['The section ids list is invalid for the current draft.'],
        ]);
    }
}
