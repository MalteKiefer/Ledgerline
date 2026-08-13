<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Server-side video handling via ffmpeg/ffprobe (already in the image, array-argv
 * only — no shell). Probes metadata, extracts a poster frame, and produces a
 * web-friendly MP4 when the source is not directly playable. Runs on the worker.
 */
final class VideoProcessor
{
    /** Video codecs a browser can play in a compatible container. */
    private const WEB_VCODEC = ['h264', 'vp8', 'vp9', 'av1'];

    /** Audio codecs a browser can play (empty = no audio track). */
    private const WEB_ACODEC = ['aac', 'mp3', 'opus', 'vorbis', ''];

    public static function available(): bool
    {
        return BinaryProcess::available('ffmpeg') && BinaryProcess::available('ffprobe');
    }

    /**
     * ffprobe → duration/dimensions/codecs/container + capture metadata
     * (creation_time, GPS, camera) read from the container/stream tags.
     *
     * @return array{duration:?int, width:?int, height:?int, vcodec:string, acodec:string, format:string, taken_at:?string, lat:?float, lng:?float, camera:?string}|null
     */
    public static function probe(string $path): ?array
    {
        $out = BinaryProcess::run([
            'ffprobe', '-v', 'quiet', '-print_format', 'json', '-show_format', '-show_streams', $path,
        ], 30);
        if ($out === null) {
            return null;
        }
        $json = json_decode($out, true);
        if (! is_array($json)) {
            return null;
        }
        $streams = is_array($json['streams'] ?? null) ? $json['streams'] : [];
        $format = is_array($json['format'] ?? null) ? $json['format'] : [];

        // Gather tags from the container and every stream (creation_time / GPS /
        // camera can live on either). Later merges win only for empty keys.
        $tags = is_array($format['tags'] ?? null) ? $format['tags'] : [];

        $vcodec = $acodec = '';
        $width = $height = null;
        foreach ($streams as $s) {
            if (! is_array($s)) {
                continue;
            }
            $type = is_string($s['codec_type'] ?? null) ? $s['codec_type'] : '';
            $codec = is_string($s['codec_name'] ?? null) ? $s['codec_name'] : '';
            if ($type === 'video' && $vcodec === '') {
                $vcodec = $codec;
                $width = is_numeric($s['width'] ?? null) ? (int) $s['width'] : null;
                $height = is_numeric($s['height'] ?? null) ? (int) $s['height'] : null;
            } elseif ($type === 'audio' && $acodec === '') {
                $acodec = $codec;
            }
            if (is_array($s['tags'] ?? null)) {
                foreach ($s['tags'] as $k => $v) {
                    if (! array_key_exists($k, $tags)) {
                        $tags[$k] = $v;
                    }
                }
            }
        }
        if ($vcodec === '') {
            return null; // no video stream → not a video we can present
        }
        $dur = is_numeric($format['duration'] ?? null) ? (int) round((float) $format['duration']) : null;
        $fmt = is_string($format['format_name'] ?? null) ? $format['format_name'] : '';

        [$lat, $lng] = self::parseLocation($tags);

        return [
            'duration' => $dur, 'width' => $width, 'height' => $height,
            'vcodec' => $vcodec, 'acodec' => $acodec, 'format' => $fmt,
            'taken_at' => self::parseCreationTime($tags),
            'lat' => $lat, 'lng' => $lng,
            'camera' => self::parseCamera($tags),
        ];
    }

    /**
     * creation_time tag (ISO 8601, usually UTC) → 'Y-m-d H:i:s' UTC, or null.
     *
     * @param  array<array-key, mixed>  $tags
     */
    private static function parseCreationTime(array $tags): ?string
    {
        // Prefer the QuickTime creation date (the true, local capture time with a
        // timezone offset) over creation_time, which on Apple/edited videos is
        // often the later UTC mux/copy date — not when it was filmed.
        foreach (['com.apple.quicktime.creationdate', 'creation_time', 'date'] as $key) {
            $raw = $tags[$key] ?? null;
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }
            try {
                $dt = new \DateTimeImmutable(trim($raw));
            } catch (\Throwable) {
                continue;
            }
            $y = (int) $dt->format('Y');
            if ($y < 1970 || $y > 2100) {
                continue; // reject the epoch/placeholder some cameras write
            }

            return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        }

        return null;
    }

    /**
     * QuickTime ISO 6709 location tag (e.g. "+52.5200+013.4050+000.000/") → [lat, lng].
     *
     * @param  array<array-key, mixed>  $tags
     * @return array{0:?float, 1:?float}
     */
    private static function parseLocation(array $tags): array
    {
        foreach (['com.apple.quicktime.location.ISO6709', 'location', 'location-eng'] as $key) {
            $raw = $tags[$key] ?? null;
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            if (preg_match('/([+-]\d+(?:\.\d+)?)([+-]\d+(?:\.\d+)?)/', $raw, $m) === 1) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat !== 0.0 || $lng !== 0.0)) {
                    return [$lat, $lng];
                }
            }
        }

        return [null, null];
    }

    /**
     * make + model tags → "Apple iPhone 15", or null.
     *
     * @param  array<array-key, mixed>  $tags
     */
    private static function parseCamera(array $tags): ?string
    {
        $make = is_string($tags['com.apple.quicktime.make'] ?? null) ? trim($tags['com.apple.quicktime.make']) : '';
        $model = is_string($tags['com.apple.quicktime.model'] ?? null) ? trim($tags['com.apple.quicktime.model']) : '';
        $camera = trim($make.' '.$model);

        return $camera !== '' ? mb_substr($camera, 0, 190) : null;
    }

    /** Extract a single poster frame (JPEG) to $out. Returns true on success. */
    public static function poster(string $src, string $out, ?int $duration): bool
    {
        // ~1s in, or 10% for very short clips.
        $at = $duration !== null && $duration > 2 ? min(3, (int) max(1, (int) floor($duration * 0.1))) : 0;
        BinaryProcess::run([
            'ffmpeg', '-y', '-ss', (string) $at, '-i', $src,
            '-frames:v', '1', '-vf', "scale='min(1280,iw)':-2", '-q:v', '3', $out,
        ], 90);

        return is_file($out) && filesize($out) > 0;
    }

    /**
     * Decide the playback rendition for a probed source.
     *   'none'   — already web-playable (mp4/webm + web codecs): serve original
     *   'remux'  — web codecs in a non-web container: fast stream-copy into MP4
     *   'transcode' — otherwise: re-encode to H.264/AAC MP4
     *
     * @param  array{vcodec:string, acodec:string, format:string}  $probe
     */
    public static function playbackPlan(array $probe): string
    {
        $vOk = in_array($probe['vcodec'], self::WEB_VCODEC, true);
        $aOk = in_array($probe['acodec'], self::WEB_ACODEC, true);
        $container = $probe['format'];
        $webContainer = str_contains($container, 'mp4') || str_contains($container, 'webm') || str_contains($container, 'm4v');

        if ($vOk && $aOk && $webContainer) {
            return 'none';
        }
        if ($vOk && $aOk) {
            return 'remux';
        }

        return 'transcode';
    }

    /** Fast remux (stream copy) into a faststart MP4. */
    public static function remux(string $src, string $out): bool
    {
        BinaryProcess::run([
            'ffmpeg', '-y', '-i', $src, '-c', 'copy', '-movflags', '+faststart', $out,
        ], 600);

        return is_file($out) && filesize($out) > 0;
    }

    /** Full transcode to a web-friendly H.264/AAC MP4 (bounded to 1080p). */
    public static function transcode(string $src, string $out): bool
    {
        BinaryProcess::run([
            'ffmpeg', '-y', '-i', $src,
            '-vf', "scale='min(1920,iw)':-2",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '24', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '128k',
            '-movflags', '+faststart', $out,
        ], 3600);

        return is_file($out) && filesize($out) > 0;
    }
}
