<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Hostinger Mail API (Agentic Mail)
    |--------------------------------------------------------------------------
    |
    | The Mail API is a REST API over HTTPS (443) at api.mail.hostinger.com —
    | deliberately separate from the hosting API at developers.hostinger.com
    | (different base URL, different tokens, different SDKs).
    |
    | Tokens are created in hPanel → Agentic Mail → API access and are scoped
    | to ONE mail order and optionally to specific mailboxes. The token is a
    | secret: env only, never committed.
    |
    */
    'api_base_url' => env('HOSTINGER_MAIL_API_BASE_URL', 'https://api.mail.hostinger.com'),
    'api_token' => env('HOSTINGER_MAIL_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Sending mailbox
    |--------------------------------------------------------------------------
    |
    | The mailbox the receipt email is sent FROM. Both identifiers come from
    | GET /api/v1/me (see MailSubstrate::account()). When resource_id is
    | empty the substrate resolves the first mailbox on the token's order.
    |
    */
    'sending_mailbox' => [
        'resource_id' => env('HOSTINGER_MAIL_SENDING_MAILBOX_ID'),
        'address' => env('HOSTINGER_MAIL_SENDING_MAILBOX'),
    ],

    'from_display_name' => env('HOSTINGER_MAIL_FROM_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Webhook endpoints are registered in hPanel → Agentic Mail → Webhooks
    | (HTTPS only) with a secret key used to verify request signatures.
    | The signature header name is configurable here.
    |
    */
    'webhook' => [
        'secret' => env('HOSTINGER_MAIL_WEBHOOK_SECRET'),
        'signature_header' => env('HOSTINGER_MAIL_WEBHOOK_SIGNATURE_HEADER', 'X-Webhook-Signature'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Dry run
    |--------------------------------------------------------------------------
    |
    | When true, sends are resolved and composed but never leave the system
    | (Result::success with `dry_run` set). Lets the whole pipeline be
    | exercised deterministically before real donor mail is enabled.
    |
    */
    'dry_run' => env('HOSTINGER_MAIL_DRY_RUN', false),

];
