<?php

declare(strict_types=1);

return [
    'heading' => 'Downloads',
    'subheading' => 'Your export archives. Prepared in the background and kept for 7 days.',
    'empty' => 'No downloads yet. Export photos or files and they will appear here.',
    'refresh' => 'Refresh',
    'settings' => 'Download settings',
    'queued_toast' => 'Export started — it will appear under Downloads when ready.',
    'new_ready' => 'New downloads ready',

    'title' => [
        'gallery' => '{1}:count photo|[2,*]:count photos',
        'files' => '{1}:count item|[2,*]:count items',
    ],

    'status' => [
        'queued' => 'Queued',
        'processing' => 'Preparing…',
        'ready' => 'Ready',
        'failed' => 'Failed',
    ],

    'source' => [
        'gallery' => 'Gallery',
        'files' => 'Files',
    ],
    'variant' => [
        'original' => 'Original',
        'edited' => 'Edited',
    ],

    'part' => 'Part :n',
    'expires' => 'Expires :when',
    'download' => 'Download',
    'delete' => 'Delete',
    'delete_selected' => 'Delete selected',
    'selected' => ':count selected',
    'confirm_delete' => 'Delete the selected downloads? The files are removed immediately.',
    'building_hint' => 'This export is still being prepared — it will appear ready shortly.',

    'error' => [
        'too_many' => 'You already have :max exports being prepared. Wait for one to finish before starting another.',
        'stuck' => 'Preparation was interrupted and did not finish. Please start the export again.',
    ],

    'notify' => [
        'ready_title' => 'Download ready',
        'ready_body' => 'Your export ":title" is ready to download.',
        'failed_title' => 'Export failed',
        'failed_body' => 'Your export ":title" could not be prepared.',
    ],

    'settings_page' => [
        'heading' => 'Downloads & exports',
        'subheading' => 'How export archives are built and how you are told they are ready.',
        'zip_heading' => 'Maximum zip part size',
        'zip_hint' => 'Larger exports are split into several zip parts so no single file exceeds this. 0 = one single zip (no limit).',
        'files_max' => 'Files export — max MB per zip',
        'gallery_max' => 'Gallery export — max MB per zip',
        'notify_heading' => 'Notify when a download is ready',
        'notify_hint' => 'NTFY, mail and webhook use the credentials configured under Notifications.',
        'notify_desktop' => 'In-app bell',
        'notify_ntfy' => 'NTFY push',
        'notify_mail' => 'E-mail',
        'notify_webhook' => 'Webhook',
        'save' => 'Save',
    ],
];
