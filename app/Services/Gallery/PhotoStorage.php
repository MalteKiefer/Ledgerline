<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

/**
 * Stores an uploaded photo under a date-structured object-storage prefix
 * (photos/{Y}/{m}/…) with a thumbnail and a medium rendition, and reads its
 * capture date and dimensions. Kept independent of the files module.
 */
class PhotoStorage
{
    private const THUMB_WIDTH = 400;

    private const MEDIUM_WIDTH = 1600;

    /**
     * @return array{
     *   uuid: string, disk_path: string, thumb_path: string, medium_path: string,
     *   mime_type: string, size: int, width: ?int, height: ?int, checksum: ?string, taken_at: Carbon
     * }
     */
    public function store(UploadedFile $upload): array
    {
        $disk = Storage::disk(config('files.disk'));
        $manager = new ImageManager(new Driver);

        $uuid = (string) Str::uuid();
        $takenAt = $this->takenAt($upload);
        $dir = 'photos/'.$takenAt->format('Y/m');
        $ext = strtolower($upload->getClientOriginalExtension() ?: 'jpg');

        $originalPath = "{$dir}/{$uuid}.{$ext}";
        $thumbPath = "{$dir}/thumb/{$uuid}.jpg";
        $mediumPath = "{$dir}/medium/{$uuid}.jpg";

        $image = $manager->decodePath($upload->getRealPath());
        $width = $image->width();
        $height = $image->height();

        $disk->put($originalPath, file_get_contents($upload->getRealPath()));
        $disk->put($thumbPath, (string) $image->scaleDown(width: self::THUMB_WIDTH)->encode(new JpegEncoder(quality: 75)));

        $medium = $manager->decodePath($upload->getRealPath());
        $disk->put($mediumPath, (string) $medium->scaleDown(width: self::MEDIUM_WIDTH)->encode(new JpegEncoder(quality: 82)));

        return [
            'uuid' => $uuid,
            'disk_path' => $originalPath,
            'thumb_path' => $thumbPath,
            'medium_path' => $mediumPath,
            'mime_type' => $upload->getMimeType() ?: 'image/jpeg',
            'size' => (int) $upload->getSize(),
            'width' => $width,
            'height' => $height,
            'checksum' => hash_file('sha256', $upload->getRealPath()) ?: null,
            'taken_at' => $takenAt,
        ];
    }

    /**
     * Capture date from EXIF DateTimeOriginal, falling back to now.
     */
    private function takenAt(UploadedFile $upload): Carbon
    {
        if (function_exists('exif_read_data') && in_array($upload->getMimeType(), ['image/jpeg', 'image/tiff'], true)) {
            $data = @exif_read_data($upload->getRealPath());
            $raw = $data['DateTimeOriginal'] ?? ($data['DateTime'] ?? null);

            if (is_string($raw)) {
                $parsed = Carbon::createFromFormat('Y:m:d H:i:s', $raw);

                if ($parsed !== false) {
                    return $parsed;
                }
            }
        }

        return Carbon::now();
    }
}
