<?php

namespace App\Support\Media;

use App\Models\Media;
use App\Models\User;
use App\Models\Website;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MediaUploader
{
    public function __construct(
        private ?Closure $uuidFactory = null,
    ) {}

    public function upload(Website $website, User $user, UploadedFile $file): Media
    {
        $imageInfo = @getimagesize($file->getRealPath());

        if ($imageInfo === false) {
            throw new RuntimeException('Uploaded file dimensions could not be determined.');
        }

        $detectedMime = is_string($imageInfo['mime'] ?? null)
            ? $imageInfo['mime']
            : ($file->getMimeType() ?? '');

        if (! MediaRasterImage::isAllowedMimeType($detectedMime)) {
            throw new RuntimeException('Uploaded file has an unsupported MIME type.');
        }

        $extension = MediaRasterImage::extensionForMimeType($detectedMime);

        if ($extension === null) {
            throw new RuntimeException('Uploaded file has an unsupported MIME type.');
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];
        $size = (int) $file->getSize();
        $originalName = basename($file->getClientOriginalName());

        if (strlen($originalName) > 255) {
            throw new RuntimeException('The original filename is too long.');
        }

        $disk = MediaRasterImage::DISK;
        $path = $this->allocateStoragePath($website, $extension, $disk);

        $stored = Storage::disk($disk)->put($path, $file->get());

        if ($stored === false) {
            throw new RuntimeException('Failed to store uploaded media file.');
        }

        try {
            return Media::query()->create([
                'website_id' => $website->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $originalName,
                'mime_type' => $detectedMime,
                'extension' => $extension,
                'size' => $size,
                'width' => $width,
                'height' => $height,
                'alt_text' => null,
                'title' => null,
                'source' => MediaRasterImage::SOURCE_UPLOAD,
                'created_by' => $user->id,
            ]);
        } catch (\Throwable $exception) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (\Throwable $cleanupException) {
                Log::warning('Failed to delete media file after database failure.', [
                    'disk' => $disk,
                    'path' => $path,
                    'cleanup_exception' => $cleanupException->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    private function allocateStoragePath(Website $website, string $extension, string $disk): string
    {
        do {
            $path = sprintf(
                'websites/%d/media/%s.%s',
                $website->id,
                $this->generateUuid(),
                $extension,
            );
        } while (Storage::disk($disk)->exists($path));

        return $path;
    }

    private function generateUuid(): string
    {
        $factory = $this->uuidFactory ?? static fn (): string => (string) Str::uuid();

        return $factory();
    }
}
