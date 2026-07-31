<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Support\BinaryProcess;
use RuntimeException;

/**
 * Reads video metadata (ffprobe) and extracts a poster frame (ffmpeg). Binary
 * paths come from the gallery settings, falling back to an environment variable
 * and finally the system PATH, so the same code runs locally (Homebrew ffmpeg)
 * and on Laravel Cloud (a static build installed by deploy/ffmpeg.sh).
 */
class VideoProcessor
{
    /**
     * Probe a local video file for its dimensions, duration and a full dump.
     *
     * @return array{width: ?int, height: ?int, duration: ?int, raw: ?array<array-key, mixed>}
     */
    public function probe(string $localPath): array
    {
        $out = ['width' => null, 'height' => null, 'duration' => null, 'raw' => null];

        $stdout = BinaryProcess::run([
            $this->ffprobe(),
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $localPath,
        ], 120);

        if ($stdout === null) {
            return $out;
        }

        $data = json_decode($stdout, true);
        if (! is_array($data)) {
            return $out;
        }

        $out['raw'] = $data;

        $streams = $data['streams'] ?? [];
        if (is_array($streams)) {
            foreach ($streams as $stream) {
                if (! is_array($stream) || ($stream['codec_type'] ?? null) !== 'video') {
                    continue;
                }

                $width = isset($stream['width']) && is_numeric($stream['width']) ? (int) $stream['width'] : null;
                $height = isset($stream['height']) && is_numeric($stream['height']) ? (int) $stream['height'] : null;

                // A rotated (portrait) video encodes landscape dimensions; swap
                // them so the stored size matches how it is displayed.
                if ($width !== null && $height !== null && $this->isSideways($stream)) {
                    [$width, $height] = [$height, $width];
                }

                $out['width'] = $width;
                $out['height'] = $height;
                break;
            }
        }

        $format = $data['format'] ?? null;
        $duration = is_array($format) ? ($format['duration'] ?? null) : null;
        if (is_numeric($duration)) {
            $out['duration'] = (int) round((float) $duration);
        }

        return $out;
    }

    /**
     * Whether a video stream is rotated a quarter turn (portrait), from either
     * the display-matrix side data or the legacy rotate tag.
     *
     * @param  array<array-key, mixed>  $stream
     */
    private function isSideways(array $stream): bool
    {
        $tags = $stream['tags'] ?? null;
        $rotation = is_array($tags) ? ($tags['rotate'] ?? null) : null;

        $sideData = $stream['side_data_list'] ?? [];
        if (is_array($sideData)) {
            foreach ($sideData as $side) {
                if (is_array($side) && isset($side['rotation'])) {
                    $rotation = $side['rotation'];
                    break;
                }
            }
        }

        return is_numeric($rotation) && abs((int) $rotation) % 180 === 90;
    }

    /**
     * Extract a single poster frame at the given second into a JPEG file.
     *
     * @throws RuntimeException when the frame cannot be produced
     */
    public function poster(string $localPath, int $second, string $destJpg): void
    {
        // Try the requested second, then fall back to the very first frame:
        // short clips (e.g. ~1s Live Photo videos) have no frame at second 1.
        foreach ([max(0, $second), 0] as $ss) {
            $stdout = BinaryProcess::run([
                $this->ffmpeg(),
                '-y',
                '-ss', (string) $ss,
                '-i', $localPath,
                '-frames:v', '1',
                '-q:v', '3',
                $destJpg,
            ], 120);

            if ($stdout !== null && is_file($destJpg) && filesize($destJpg) > 0) {
                return;
            }

            if ($ss === 0) {
                break;
            }
        }

        throw new RuntimeException('ffmpeg could not extract a poster frame.');
    }

    /**
     * The resolved ffmpeg binary path: a per-workspace override, otherwise the
     * configured path (read through config so it survives config caching).
     */
    public function binaryPath(): string
    {
        // Config/env only (like exiftool): the ffmpeg binary is executed by the
        // worker, so it must not be settable from the app UI/database.
        $path = config('gallery.ffmpeg_path', 'ffmpeg');

        return is_string($path) && $path !== '' ? $path : 'ffmpeg';
    }

    private function ffmpeg(): string
    {
        return $this->binaryPath();
    }

    private function ffprobe(): string
    {
        $ffmpeg = $this->ffmpeg();

        // ffprobe ships alongside ffmpeg; derive its path from the ffmpeg one.
        $sibling = preg_replace('/ffmpeg(\.exe)?$/', 'ffprobe$1', $ffmpeg);

        return $sibling ?: 'ffprobe';
    }
}
