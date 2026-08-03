<?php

declare(strict_types=1);

return [
    'title' => 'Mail',
    'subheading' => 'IMAP accounts archived into your account. Reading archived mail is not available yet.',

    // List
    'loading' => 'Loading accounts…',
    'empty' => 'No mail accounts yet. Add one to start archiving.',
    'load_failed' => 'Could not load your mail accounts.',
    'status_idle' => 'Idle',
    'status_syncing' => 'Syncing…',
    'status_error' => 'Error',
    'disabled' => 'Disabled',
    'last_synced' => 'Last synced :when',
    'never_synced' => 'Never synced',
    'message_count' => ':count archived messages',
    'sync_now' => 'Sync now',
    'syncing' => 'Syncing…',
    'sync_failed' => 'Could not start the sync.',

    // Add/edit
    'add_account' => 'Add account',
    'new_account' => 'New account',
    'edit_account' => 'Edit account',
    'name' => 'Name',
    'host' => 'Host',
    'host_placeholder' => 'imap.example.com',
    'port' => 'Port',
    'username' => 'Username',
    'username_placeholder' => 'name@example.com',
    'password' => 'Password',
    'password_placeholder' => '••••••••',
    'password_hint' => 'Leave blank to keep the current password.',
    'encryption' => 'Encryption',
    'encryption_ssl' => 'SSL',
    'encryption_tls' => 'TLS',
    'encryption_starttls' => 'STARTTLS',
    'encryption_none' => 'None',
    'folders' => 'Folders',
    'folders_placeholder' => 'Add a folder…',
    'folders_hint' => 'Leave empty to sync every folder.',
    'backfill_since' => 'Backfill since',
    'backfill_hint' => 'Only archive messages received on or after this date. Leave empty to archive full history.',
    'enabled' => 'Enabled',
    'save_failed' => 'Could not save the account. Check the fields and try again.',
    'delete_confirm' => 'Delete this account? Its archived messages are removed as well. This cannot be undone.',
    'delete_failed' => 'Could not delete the account.',
];
