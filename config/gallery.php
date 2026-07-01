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

];
