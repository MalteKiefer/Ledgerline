<?php

declare(strict_types=1);

return [
    // Per-user quota for stored contact avatar blobs (MiB). 0 = unlimited.
    'quota_mb' => (int) env('CONTACTS_QUOTA_MB', 0),

    // Max size of a single avatar upload (MiB). Avatars are small; this only
    // bounds a single POST body.
    'max_upload_mb' => (int) env('CONTACTS_MAX_UPLOAD_MB', 16),

    // Grace before an unreferenced avatar blob on disk is swept (so an in-flight
    // upload not yet in the sealed manifest isn't reclaimed).
    'blob_orphan_grace_hours' => (int) env('CONTACTS_BLOB_ORPHAN_GRACE_HOURS', 24),
];
