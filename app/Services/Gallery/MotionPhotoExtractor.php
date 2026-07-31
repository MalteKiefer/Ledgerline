<?php

declare(strict_types=1);

namespace App\Services\Gallery;

/**
 * Extracts the MP4 clip embedded in a motion photo (Google/Samsung), which is
 * appended after the JPEG data. The clip's start is found from the XMP markers
 * (Container Item length or the older MicroVideo offset), falling back to
 * scanning for the MP4 "ftyp" box after the JPEG end marker. Pure byte parsing,
 * no ffmpeg required.
 */
class MotionPhotoExtractor
{
    /**
     * Return the embedded MP4 bytes, or null when the file is not a motion photo.
     */
    public function extract(string $localPath): ?string
    {
        $data = @file_get_contents($localPath);
        if ($data === false || $data === '') {
            return null;
        }

        $size = strlen($data);
        $start = $this->offsetFromXmp($data, $size) ?? $this->offsetFromScan($data);

        if ($start === null || $start <= 0 || $start >= $size) {
            return null;
        }

        $clip = substr($data, $start);

        // Sanity check: an MP4 begins with an "ftyp" box within the first bytes.
        return str_contains(substr($clip, 0, 16), 'ftyp') ? $clip : null;
    }

    /**
     * Resolve the clip start from the XMP markers, which express the video as a
     * number of bytes measured from the end of the file.
     */
    private function offsetFromXmp(string $data, int $size): ?int
    {
        // Newer container format: the video item carries its byte length.
        if (preg_match('/Semantic="MotionPhoto"[^>]*?Length="(\d+)"/s', $data, $m)
            || preg_match('/Length="(\d+)"[^>]*?Semantic="MotionPhoto"/s', $data, $m)) {
            $length = (int) $m[1];
            if ($length > 0) {
                return $size - $length;
            }
        }

        // Older MicroVideo format: an explicit offset from the end of the file.
        if (preg_match('/MicroVideoOffset="(\d+)"/', $data, $m)) {
            $offset = (int) $m[1];
            if ($offset > 0) {
                return $size - $offset;
            }
        }

        return null;
    }

    /**
     * Fall back to locating the first MP4 "ftyp" box after the JPEG end marker.
     */
    private function offsetFromScan(string $data): ?int
    {
        $eoi = strpos($data, "\xFF\xD9");
        if ($eoi === false) {
            return null;
        }

        $ftyp = strpos($data, 'ftyp', $eoi);
        if ($ftyp === false || $ftyp < 4) {
            return null;
        }

        // "ftyp" is preceded by its 4-byte box size; the box starts there.
        return $ftyp - 4;
    }
}
