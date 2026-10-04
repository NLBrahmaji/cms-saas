<?php

namespace App\Support\Page;

use App\Models\PageVersionSeoSetting;

class PageSeoEffectiveState
{
    /**
     * @var list<string>
     */
    public const FIELDS = [
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
        'og_image_id',
        'canonical_url',
        'robots_index',
        'robots_follow',
    ];

    /**
     * @var list<string>
     */
    public const WRITABLE_FIELDS = [
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
        'canonical_url',
        'robots_index',
        'robots_follow',
    ];

    /**
     * @return array{
     *     meta_title: ?string,
     *     meta_description: ?string,
     *     og_title: ?string,
     *     og_description: ?string,
     *     og_image_id: ?int,
     *     canonical_url: ?string,
     *     robots_index: ?bool,
     *     robots_follow: ?bool
     * }
     */
    public static function fromSeoRow(?PageVersionSeoSetting $seo): array
    {
        if ($seo === null) {
            return self::empty();
        }

        return [
            'meta_title' => $seo->meta_title,
            'meta_description' => $seo->meta_description,
            'og_title' => $seo->og_title,
            'og_description' => $seo->og_description,
            'og_image_id' => $seo->og_image_id !== null ? (int) $seo->og_image_id : null,
            'canonical_url' => $seo->canonical_url,
            'robots_index' => $seo->robots_index,
            'robots_follow' => $seo->robots_follow,
        ];
    }

    /**
     * @return array{
     *     meta_title: null,
     *     meta_description: null,
     *     og_title: null,
     *     og_description: null,
     *     og_image_id: null,
     *     canonical_url: null,
     *     robots_index: null,
     *     robots_follow: null
     * }
     */
    public static function empty(): array
    {
        return [
            'meta_title' => null,
            'meta_description' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image_id' => null,
            'canonical_url' => null,
            'robots_index' => null,
            'robots_follow' => null,
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
