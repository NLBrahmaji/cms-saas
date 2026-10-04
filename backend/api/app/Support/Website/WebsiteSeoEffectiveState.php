<?php

namespace App\Support\Website;

use App\Models\WebsiteSeoSetting;

class WebsiteSeoEffectiveState
{
    /**
     * @var list<string>
     */
    public const FIELDS = [
        'title_suffix',
        'default_description',
        'default_og_image_id',
        'robots_index',
        'robots_follow',
    ];

    /**
     * @return array{
     *     title_suffix: ?string,
     *     default_description: ?string,
     *     default_og_image_id: ?int,
     *     robots_index: bool,
     *     robots_follow: bool
     * }
     */
    public static function fromSeoRow(?WebsiteSeoSetting $seo): array
    {
        if ($seo === null) {
            return self::empty();
        }

        return [
            'title_suffix' => $seo->title_suffix,
            'default_description' => $seo->default_description,
            'default_og_image_id' => $seo->default_og_image_id !== null ? (int) $seo->default_og_image_id : null,
            'robots_index' => (bool) $seo->robots_index,
            'robots_follow' => (bool) $seo->robots_follow,
        ];
    }

    /**
     * @return array{
     *     title_suffix: null,
     *     default_description: null,
     *     default_og_image_id: null,
     *     robots_index: true,
     *     robots_follow: true
     * }
     */
    public static function empty(): array
    {
        return [
            'title_suffix' => null,
            'default_description' => null,
            'default_og_image_id' => null,
            'robots_index' => true,
            'robots_follow' => true,
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
