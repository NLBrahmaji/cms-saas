<?php

namespace App\Navigation;

/**
 * Future URL validation contract for NavigationItemType::Url targets.
 *
 * Relative site paths (e.g. /contact) and absolute http/https URLs are intended
 * to be accepted. Dangerous non-web schemes must be rejected when validation
 * is implemented at the HTTP layer.
 */
final class NavigationItemUrlContract
{
    /**
     * @var list<string>
     */
    public const FORBIDDEN_SCHEMES = [
        'javascript',
        'data',
        'file',
    ];

    public static function hasForbiddenScheme(string $url): bool
    {
        $trimmed = ltrim($url);

        if (! str_contains($trimmed, ':')) {
            return false;
        }

        $scheme = strtolower((string) strtok($trimmed, ':'));

        return in_array($scheme, self::FORBIDDEN_SCHEMES, true);
    }

    /**
     * Whether a URL matches the intended future allow-list shape (not full validation).
     */
    public static function matchesIntendedFutureShape(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (self::hasForbiddenScheme($url)) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host']) || $parsed['host'] === '') {
            return false;
        }

        $scheme = strtolower($parsed['scheme']);

        return in_array($scheme, ['http', 'https'], true);
    }
}
