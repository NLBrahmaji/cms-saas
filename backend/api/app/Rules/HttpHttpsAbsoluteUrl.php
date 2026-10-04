<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HttpHttpsAbsoluteUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if (strlen($value) > 2048) {
            $fail('The :attribute must not be greater than 2048 characters.');

            return;
        }

        $parsed = parse_url($value);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host']) || $parsed['host'] === '') {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $scheme = strtolower($parsed['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }
    }
}
