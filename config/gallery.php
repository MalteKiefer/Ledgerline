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
    | cached in production. The Docker image installs ffmpeg via its Alpine base;
    | set GALLERY_FFMPEG_PATH only to point at a non-PATH binary. The matching
    | ffprobe binary is derived from this path.
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
    | Machine learning (CLIP embeddings)
    |--------------------------------------------------------------------------
    |
    | CLIP image/text embeddings come from the immich-machine-learning sidecar
    | (docker compose --profile ml). ml_url points at it on the internal network;
    | the browser stores the resulting (sealed) embedding for client-side search.
    |
    */

    'ml_enabled' => (bool) env('ML_ENABLED', false),

    'ml_url' => env('ML_URL', 'http://ml:3003'),

    'ml_clip_model' => env('ML_CLIP_MODEL', 'ViT-B-32__openai'),

    // Grace before an unreferenced gallery blob on disk is swept (so an in-flight
    // upload whose row isn't saved yet is never reaped).
    'blob_orphan_grace_hours' => (int) env('GALLERY_BLOB_ORPHAN_GRACE_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Face detection
    |--------------------------------------------------------------------------
    |
    | Faces are detected + embedded by the immich-ml sidecar (buffalo_l). The
    | browser seals the detected face crops + embeddings and clusters people
    | client-side. face_min_score filters weak detections.
    |
    */

    'face_enabled' => (bool) env('FACE_ENABLED', false),

    'face_model' => env('ML_FACE_MODEL', 'buffalo_l'),

    'face_min_score' => (float) env('GALLERY_FACE_MIN_SCORE', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Reverse-geocoding endpoint + automatic-on-upload opt-in
    |--------------------------------------------------------------------------
    |
    | A photo's GPS coordinates are reverse-geocoded to a place name through a
    | Nominatim-compatible endpoint. By default this is OpenStreetMap's public
    | server, so enabling automatic geocoding sends (coarsened) coordinates off
    | the zero-knowledge boundary to a third party.
    |
    | geocode_on_upload is therefore OFF by default: no coordinate leaves the
    | host automatically. The place-picker in the viewer still lets a user
    | resolve an address on demand. Turn it on only if you accept the OSM egress
    | or — better — point geocoder_url at a SELF-HOSTED Nominatim (docker compose
    | --profile geocode) so the lookup never leaves your network. Any
    | Nominatim-compatible /reverse endpoint (jsonv2) works as a drop-in.
    |
    */

    'geocode_on_upload' => (bool) env('GALLERY_GEOCODE_ON_UPLOAD', false),

    'geocoder_url' => rtrim((string) env('GEOCODER_URL', 'https://nominatim.openstreetmap.org'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Reverse-geocoding rate limit
    |--------------------------------------------------------------------------
    |
    | Minimum spacing (milliseconds) between Nominatim requests, enforced across
    | all workers. OpenStreetMap's usage policy allows at most one request per
    | second; bulk imports would otherwise get the server blocked. A self-hosted
    | endpoint has no such policy — set to 0 to disable spacing.
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

    /*
    |--------------------------------------------------------------------------
    | Zero-knowledge storage (blobs)
    |--------------------------------------------------------------------------
    */

    // Per-user gallery storage quota in megabytes (0 = unlimited).
    'quota_mb' => (int) env('GALLERY_QUOTA_MB', 0),

    // Max single-upload size (MB) for one gallery content blob (non-chunked).
    'max_upload_mb' => (int) env('GALLERY_MAX_UPLOAD_MB', 512),

    // Grace window (hours) before an orphaned blob (uploaded but not yet
    // referenced by the sealed index) is eligible for reconcile/sweep reclaim.
    // (Already declared above as blob_orphan_grace_hours; reused here.)

];
