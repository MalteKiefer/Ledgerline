<?php

declare(strict_types=1);

// Shared-folder blob store settings. Blobs live on the same object-storage disk
// as personal files (prefix shared-folders/). Quota is attributed to the folder
// owner and enforced against their personal files quota (see SharedFolderBlobController).
return [
    'disk' => env('FILES_DISK', 'files'),
    'max_upload_mb' => (int) env('FILES_MAX_UPLOAD_MB', 2048),
    'blob_orphan_grace_hours' => (int) env('FILES_BLOB_ORPHAN_GRACE_HOURS', 24),
];
