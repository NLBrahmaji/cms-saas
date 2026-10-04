<?php

namespace App\Support\Page;

use Illuminate\Support\Str;

class PageSlugNormalizer
{
    public const MAX_LENGTH = 255;

    public function normalize(string $slug): ?string
    {
        $normalized = Str::slug($slug);

        if ($normalized === '') {
            return null;
        }

        if (strlen($normalized) > self::MAX_LENGTH) {
            $normalized = substr($normalized, 0, self::MAX_LENGTH);
            $normalized = trim($normalized, '-');

            if ($normalized === '') {
                return null;
            }
        }

        return $normalized;
    }
}
