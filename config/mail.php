<?php

declare(strict_types=1);

return [

    // Doctrine: production launches MUST set MAIL_MAILER=smtp with a
    // real provider (SES, Mailgun, Postmark, etc.). The 'log' default
    // here is intentional for local dev — the success page and any
    // post-payment email will write to storage/logs/laravel.log
    // instead of being delivered. The launch checklist (.env.example
    // docblock + README) requires flipping this before go-live; the
    // success page promise "Receipt will be emailed" is only true
    // when MAIL_MAILER=smtp.
    'default' => env('MAIL_MAILER', 'log'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Temple Trust'),
    ],

];