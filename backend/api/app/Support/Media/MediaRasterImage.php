<?php

namespace App\Support\Media;

class MediaRasterImage
{
    public const DISK = 'public';

    public const SOURCE_UPLOAD = 'upload';

    /** 10 MiB expressed as KiB for Laravel file validation (10 * 1024 KiB). */
    public const MAX_SIZE_KIBIBYTES = 10240;

    /**
     * @var list<string>
     */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public static function extensionForMimeType(string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => null,
        };
    }

    public static function isAllowedMimeType(string $mimeType): bool
    {
        return in_array($mimeType, self::ALLOWED_MIME_TYPES, true);
    }
}
