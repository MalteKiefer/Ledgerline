<?php

declare(strict_types=1);

return [
    'title' => 'Servers',
    'subtitle' => 'Monitored over SSH — no agent is installed on the target.',
    'add' => 'Add server',
    'edit' => 'Edit server',
    'none' => 'No servers yet.',
    'refresh' => 'Refresh',
    'refresh_all' => 'Refresh all',
    'refresh_queued' => 'Refresh queued.',
    'delete_confirm' => 'Remove :name from monitoring? The server itself is not touched.',
    'group_other' => 'Ungrouped',

    // Connection form
    'name' => 'Name',
    'host' => 'Host',
    'port' => 'Port',
    'username' => 'User',
    'auth_type' => 'Authentication',
    'auth_key' => 'Private key',
    'auth_password' => 'Password',
    'password' => 'Password',
    'private_key' => 'Private key',
    'private_key_hint' => 'OpenSSH or PEM. Stored encrypted and never shown again.',
    'passphrase' => 'Key passphrase',
    'secret_kept' => 'Leave blank to keep the stored secret.',
    'group' => 'Group',
    'note' => 'Note',
    'enabled' => 'Poll on a schedule',
    'restricted_key' => 'Key is restricted on the target (forced command)',
    'restricted_key_hint' => 'Recommended. The key can then only print the snapshot, even if it is stolen.',

    // Connection test / host key
    'test' => 'Test connection',
    'testing' => 'Connecting…',
    'test_ok' => 'Connection established.',
    'fingerprint' => 'Host key fingerprint',
    'fingerprint_confirm' => 'Compare this with the target before saving. It is pinned and every later connection must match it.',
    'fingerprint_hint' => 'On the server: ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub',
    'test_first' => 'Test the connection first — the host key has to be confirmed before saving.',

    // Probe script (forced-command setup)
    'script_title' => 'Restrict the key on the target',
    'script_intro' => 'Put this script at /usr/local/bin/ll-facts (executable), then prefix the key in ~/.ssh/authorized_keys with:',
    'script_authorized' => 'command="/usr/local/bin/ll-facts",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty',
    'script_outro' => 'The key can then do nothing but print the snapshot. Use an unprivileged user, never root, and no sudo.',

    // Status / facts
    'status_ok' => 'Reachable',
    'status_fail' => 'Unreachable',
    'status_unknown' => 'Not polled yet',
    'checked' => 'Checked',
    'os' => 'Operating system',
    'kernel' => 'Kernel',
    'uptime' => 'Uptime',
    'load' => 'Load',
    'cpu' => 'CPU',
    'memory' => 'Memory',
    'swap' => 'Swap',
    'disks' => 'Filesystems',
    'ports' => 'Listening ports',
    'containers' => 'Containers',
    'updates' => 'Pending updates',
    'updates_unknown' => 'Unknown',
    'failed_units' => 'Failed services',
    'reboot_required' => 'Reboot required',
    'cores' => ':n cores',
    'history' => 'History',
    'duration' => 'Probe took :ms ms',

    // Probe failure reasons
    'error' => [
        'unsafe_host' => 'Refusing to connect to this host.',
        'no_host_key' => 'The server presented no usable host key.',
        'fingerprint_mismatch' => 'The host key does not match the pinned one — connection refused.',
        'no_credentials' => 'No credentials stored for this authentication method.',
        'auth_failed' => 'Authentication was rejected.',
        'unexpected_output' => 'The server answered, but not with a snapshot. Is the forced command set up correctly?',
    ],

    'notify' => [
        'down' => ':name is unreachable',
        'up' => ':name is reachable again',
        'reboot' => ':name needs a reboot',
        'disk' => ':name: :mount is :pct% full',
        'units' => ':name: :count service(s) failed',
    ],
];
