<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | ffmpeg binary path
    |--------------------------------------------------------------------------
    |
    | Path to the ffmpeg binary used to generate video thumbnails. Read through
    | config (not env() directly) so it keeps working when the configuration is
    | cached in production. On Laravel Cloud this is installed by
    | deploy/ffmpeg.sh; set GALLERY_FFMPEG_PATH to the installed binary. The
    | matching ffprobe binary is derived from this path.
    |
    | A per-workspace override may be set in the gallery settings, which takes
    | precedence over this value.
    |
    */

    'ffmpeg_path' => env('GALLERY_FFMPEG_PATH', 'ffmpeg'),

    /*
    |--------------------------------------------------------------------------
    | exiftool binary path
    |--------------------------------------------------------------------------
    |
    | Path to the exiftool binary used to read still-image metadata that PHP's
    | exif_read_data() cannot (HEIC/HEIF/AVIF and Apple's Live Photo tags). Read
    | through config so it survives configuration caching. The production image
    | installs it via libimage-exiftool-perl; set EXIFTOOL_PATH to override.
    |
    */

    'exiftool_path' => env('EXIFTOOL_PATH', 'exiftool'),

    /*
    |--------------------------------------------------------------------------
    | Content-based duplicate detection (machine learning + perceptual hash)
    |--------------------------------------------------------------------------
    |
    | CLIP image embeddings come from the immich-machine-learning sidecar
    | (docker compose --profile ml). ml_url points at it on the internal network.
    | duplicate_threshold is the minimum cosine similarity (0..1) for two photos
    | to count as duplicates; phash_max_distance is the Hamming distance under
    | which the cheap perceptual-hash pre-pass treats two images as near-identical.
    |
    */

    'ml_enabled' => (bool) env('ML_ENABLED', false),

    'ml_url' => env('ML_URL', 'http://ml:3003'),

    'ml_clip_model' => env('ML_CLIP_MODEL', 'ViT-B-32__openai'),

    'duplicate_threshold' => (float) env('GALLERY_DUPLICATE_THRESHOLD', 0.92),

    'phash_max_distance' => (int) env('GALLERY_PHASH_MAX_DISTANCE', 6),

    /*
    |--------------------------------------------------------------------------
    | Reverse-geocoding rate limit
    |--------------------------------------------------------------------------
    |
    | Minimum spacing (milliseconds) between Nominatim requests, enforced across
    | all workers. OpenStreetMap's usage policy allows at most one request per
    | second; bulk imports would otherwise get the server blocked.
    |
    */

    'geocode_interval_ms' => (int) env('GALLERY_GEOCODE_INTERVAL_MS', 1100),

    /*
    |--------------------------------------------------------------------------
    | Reverse-geocoding grid
    |--------------------------------------------------------------------------
    |
    | Coordinates are snapped to a grid of this size (kilometres) before being
    | looked up and cached, so photos taken close together share one result and
    | one request. Larger values save requests but make the stored place name
    | coarser (a nearby point may borrow a neighbour's address). 0.5 keeps the
    | same spot/building together while staying accurate.
    |
    */

    'geocode_grid_km' => (float) env('GALLERY_GEOCODE_GRID_KM', 0.5),

];
