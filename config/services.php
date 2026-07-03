<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pocket-ID (OIDC / OAuth2)
    |--------------------------------------------------------------------------
    |
    | Pocket-ID is the sole identity provider for this application. The
    | credentials below are issued when registering an OIDC client in the
    | Pocket-ID admin UI. PKCE is enabled by default to harden the
    | authorization-code exchange against interception.
    |
    */

    'pocketid' => [
        'base_url' => env('POCKETID_BASE_URL'),
        'client_id' => env('POCKETID_CLIENT_ID'),
        'client_secret' => env('POCKETID_CLIENT_SECRET'),
        'redirect' => env('POCKETID_REDIRECT_URI'),
        'use_pkce' => env('POCKETID_USE_PKCE', true),

        // This is a single-tenant application. If neither allow-list is set, the
        // first identity to sign in claims the account and every other subject
        // is rejected ("first user wins"). Set either list to pin sign-in to
        // explicit OIDC subject IDs and/or verified e-mail addresses
        // (comma-separated) regardless of who authenticates first.
        'allowed_subs' => array_filter(array_map(
            'trim',
            explode(',', (string) env('POCKETID_ALLOWED_SUBS', ''))
        )),
        'allowed_emails' => array_filter(array_map(
            'trim',
            explode(',', (string) env('POCKETID_ALLOWED_EMAILS', ''))
        )),
    ],

];
