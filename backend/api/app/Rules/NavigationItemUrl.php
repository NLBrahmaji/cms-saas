<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NavigationItemUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if ($value === '' || strlen($value) > 2048) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if (str_starts_with($value, '//')) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        if (str_starts_with($value, '/')) {
            return;
        }

        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $value) === 1) {
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

            return;
        }

        $fail('The :attribute must be a valid URL.');
    }
}
