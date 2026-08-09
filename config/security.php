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
    | Default 'self' — same-origin framing only (clickjacking-safe for an
    | internet-facing deployment); SecurityHeaders also emits
    | X-Frame-Options: SAMEORIGIN. Set 'none' to refuse ALL framing (XFO: DENY),
    | or a CSP source list to allow specific embedders, e.g.
    | "'self' https://dashboard.example". A literal "*" (framing by ANY origin)
    | is a clickjacking exposure and is REFUSED on a TLS/internet-facing box
    | (falls back to 'self' when FORCE_HTTPS / secure cookies are on) — see
    | SecurityHeaders::frameAncestors(). When set to anything other than 'none'
    | X-Frame-Options 'DENY' is dropped (it cannot express an allowlist) and CSP
    | frame-ancestors is authoritative.
    |
    */

    'frame_ancestors' => env('FRAME_ANCESTORS', "'self'"),

];
