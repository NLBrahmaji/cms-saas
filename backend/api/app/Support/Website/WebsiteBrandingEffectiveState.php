<?php

namespace App\Support\Website;

use App\Models\WebsiteBranding;

class WebsiteBrandingEffectiveState
{
    /**
     * @var list<string>
     */
    public const FIELDS = [
        'logo_media_id',
        'logo_light_media_id',
        'logo_dark_media_id',
        'favicon_media_id',
    ];

    /**
     * @return array{
     *     logo_media_id: ?int,
     *     logo_light_media_id: ?int,
     *     logo_dark_media_id: ?int,
     *     favicon_media_id: ?int
     * }
     */
    public static function fromBrandingRow(?WebsiteBranding $branding): array
    {
        if ($branding === null) {
            return self::empty();
        }

        return [
            'logo_media_id' => $branding->logo_media_id !== null ? (int) $branding->logo_media_id : null,
            'logo_light_media_id' => $branding->logo_light_media_id !== null ? (int) $branding->logo_light_media_id : null,
            'logo_dark_media_id' => $branding->logo_dark_media_id !== null ? (int) $branding->logo_dark_media_id : null,
            'favicon_media_id' => $branding->favicon_media_id !== null ? (int) $branding->favicon_media_id : null,
        ];
    }

    /**
     * @return array{
     *     logo_media_id: null,
     *     logo_light_media_id: null,
     *     logo_dark_media_id: null,
     *     favicon_media_id: null
     * }
     */
    public static function empty(): array
    {
        return [
            'logo_media_id' => null,
            'logo_light_media_id' => null,
            'logo_dark_media_id' => null,
            'favicon_media_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $effective
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public static function applyChanges(array $effective, array $changes): array
    {
        $candidate = $effective;

        foreach ($changes as $field => $value) {
            $candidate[$field] = $value;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    public static function equals(array $left, array $right): bool
    {
        foreach (self::FIELDS as $field) {
            if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
