<?php

namespace App\Support\Page;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Support\Str;

class PageSlugAllocator
{
    private const MAX_LENGTH = 255;

    private const SUFFIX_RESERVE = 5;

    private const EMPTY_NAME_BASE = 'page';

    public function baseFromName(string $name): string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            return self::EMPTY_NAME_BASE;
        }

        return $this->trimHyphens($slug);
    }

    public function allocate(Website $website, string $name): string
    {
        $base = $this->truncateBase($this->baseFromName($name));

        $candidate = $base;
        $suffix = 2;

        while ($this->slugExistsInWebsite($website, $candidate)) {
            $candidate = $this->candidateWithSuffix($base, $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function truncateBase(string $base): string
    {
        $maxBaseLength = self::MAX_LENGTH - self::SUFFIX_RESERVE;

        if (strlen($base) <= $maxBaseLength) {
            return $base;
        }

        $truncated = substr($base, 0, $maxBaseLength);

        return $this->trimHyphens($truncated) ?: self::EMPTY_NAME_BASE;
    }

    private function candidateWithSuffix(string $base, int $suffix): string
    {
        $suffixPart = '-'.$suffix;
        $maxBaseLength = self::MAX_LENGTH - strlen($suffixPart);
        $trimmedBase = strlen($base) > $maxBaseLength
            ? $this->trimHyphens(substr($base, 0, $maxBaseLength))
            : $base;

        if ($trimmedBase === '') {
            $trimmedBase = self::EMPTY_NAME_BASE;
        }

        return $trimmedBase.$suffixPart;
    }

    private function trimHyphens(string $value): string
    {
        return trim($value, '-');
    }

    private function slugExistsInWebsite(Website $website, string $slug): bool
    {
        return Page::query()
            ->where('website_id', $website->id)
            ->whereHas('draftVersion', function ($query) use ($slug): void {
                $query->where('slug', $slug);
            })
            ->exists();
    }
}
