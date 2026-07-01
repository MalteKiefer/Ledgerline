<?php

declare(strict_types=1);

namespace App\Services\Gallery;

use App\Models\CompanyProfile;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

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
     * @return array{width: ?int, height: ?int, duration: ?int, raw: ?array<string, mixed>}
     */
    public function probe(string $localPath): array
    {
        $out = ['width' => null, 'height' => null, 'duration' => null, 'raw' => null];

        $process = new Process([
            $this->ffprobe(),
            '-v', 'quiet',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $localPath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            return $out;
        }

        $data = json_decode($process->getOutput(), true);
        if (! is_array($data)) {
            return $out;
        }

        $out['raw'] = $data;

        foreach ($data['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? null) === 'video') {
                $width = isset($stream['width']) ? (int) $stream['width'] : null;
                $height = isset($stream['height']) ? (int) $stream['height'] : null;

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

        if (isset($data['format']['duration'])) {
            $out['duration'] = (int) round((float) $data['format']['duration']);
        }

        return $out;
    }

    /**
     * Whether a video stream is rotated a quarter turn (portrait), from either
     * the display-matrix side data or the legacy rotate tag.
     *
     * @param  array<string, mixed>  $stream
     */
    private function isSideways(array $stream): bool
    {
        $rotation = $stream['tags']['rotate'] ?? null;

        foreach ($stream['side_data_list'] ?? [] as $side) {
            if (isset($side['rotation'])) {
                $rotation = $side['rotation'];
                break;
            }
        }

        return $rotation !== null && abs((int) $rotation) % 180 === 90;
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
            $process = new Process([
                $this->ffmpeg(),
                '-y',
                '-ss', (string) $ss,
                '-i', $localPath,
                '-frames:v', '1',
                '-q:v', '3',
                $destJpg,
            ]);
            $process->setTimeout(120);
            $process->run();

            if ($process->isSuccessful() && is_file($destJpg) && filesize($destJpg) > 0) {
                return;
            }

            $error = $process->getErrorOutput();
            if ($ss === 0) {
                break;
            }
        }

        throw new RuntimeException('ffmpeg could not extract a poster frame: '.($error ?? ''));
    }

    /**
     * Whether ffmpeg appears to be available.
     */
    public function available(): bool
    {
        try {
            $process = new Process([$this->ffmpeg(), '-version']);
            $process->setTimeout(20);
            $process->run();

            return $process->isSuccessful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The resolved ffmpeg binary path: a per-workspace override, otherwise the
     * configured path (read through config so it survives config caching).
     */
    public function binaryPath(): string
    {
        return CompanyProfile::current()->gallery_ffmpeg_path
            ?: (string) config('gallery.ffmpeg_path', 'ffmpeg');
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
