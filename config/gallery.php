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
