<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Block private / loopback outbound targets
    |--------------------------------------------------------------------------
    |
    | Server-issued outbound requests (Paperless, NTFY, webhooks) always refuse
    | link-local / cloud-metadata addresses. This app is single-tenant and
    | self-hosted, so LAN and loopback targets (e.g. a Paperless instance on the
    | same host) are permitted by default. Enable this on a hardened deployment
    | where every outbound integration lives on a public host, to additionally
    | reject all private (RFC 1918), loopback and reserved ranges.
    |
    */

    'block_private_hosts' => (bool) env('SECURITY_BLOCK_PRIVATE_HOSTS', false),

];
