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

    /*
    |--------------------------------------------------------------------------
    | CSP frame-ancestors (who may embed the app in a frame/iframe)
    |--------------------------------------------------------------------------
    |
    | Default 'none' — the app refuses all framing (clickjacking-safe) and also
    | emits X-Frame-Options: DENY. On a trusted, non-internet-facing LAN an
    | operator may allow a home-dashboard/portal to embed the app by setting
    | FRAME_ANCESTORS to a CSP source list, e.g. "'self' http://192.168.3.200:8300",
    | or "*" to permit any embedder. When set to anything other than 'none' the
    | X-Frame-Options header is dropped (it cannot express an allowlist) and CSP
    | frame-ancestors is authoritative. Keep 'none' for a public deployment.
    |
    */

    'frame_ancestors' => env('FRAME_ANCESTORS', "'none'"),

];
