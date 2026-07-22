<?php

declare(strict_types=1);

return [
    // Per-user quota for stored Explore track blobs (MiB). 0 = unlimited.
    'quota_mb' => (int) env('EXPLORE_QUOTA_MB', 0),

    // Max size of a single track upload (MiB).
    'max_upload_mb' => (int) env('EXPLORE_MAX_UPLOAD_MB', 64),

    // Grace before an unreferenced track blob on disk is swept (so an in-flight
    // upload not yet in the sealed module store isn't reclaimed).
    'blob_orphan_grace_hours' => (int) env('EXPLORE_BLOB_ORPHAN_GRACE_HOURS', 24),
];
