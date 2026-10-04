<?php

namespace App\Support\Page;

class PageSectionContentDocument
{
    public static function normalizeForComparison(mixed $content): mixed
    {
        if ($content === null) {
            return [];
        }

        if (! is_array($content)) {
            return [];
        }

        if ($content === []) {
            return [];
        }

        if (array_is_list($content)) {
            return $content;
        }

        return $content;
    }

    public static function equals(mixed $left, mixed $right): bool
    {
        return self::canonicalize($left) === self::canonicalize($right);
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => self::canonicalize($item),
                $value,
            );
        }

        $keys = array_keys($value);
        sort($keys, SORT_STRING);

        $canonical = [];

        foreach ($keys as $key) {
            $canonical[$key] = self::canonicalize($value[$key]);
        }

        return $canonical;
    }
}
