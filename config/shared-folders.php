<?php

declare(strict_types=1);

// Shared-folder blob store settings. Blobs live on the same object-storage disk
// as personal files (prefix shared-folders/). Quota is attributed to the folder
// owner and enforced against their personal files quota (see SharedFolderBlobController).
return [
    'disk' => env('FILES_DISK', 'files'),
    'max_upload_mb' => (int) env('FILES_MAX_UPLOAD_MB', 2048),
    // Owner attribution: a shared folder's bytes count against the folder owner's
    // personal files quota, so this mirrors files.quota_mb (0 = unlimited). Kept
    // as its own key for completeness; SharedFolderBlobController enforces against
    // the owner's files quota.
    'quota_mb' => (int) env('FILES_QUOTA_MB', 0),
    'blob_orphan_grace_hours' => (int) env('FILES_BLOB_ORPHAN_GRACE_HOURS', 24),
];
