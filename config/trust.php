<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Trust public identity (SEO structured data, social links, contact)
|--------------------------------------------------------------------------
|
| Single source for the public-facing trust identity. Consumed by
| SeoMetaBuilder::trustGraph() (JSON-LD), HandleInertiaRequests (shared
| `trust` prop -> footer, header, contact page) and the email-swap
| command. Statutory credentials (PAN/TAN/80G/12A) intentionally do NOT
| live here — they stay in the `trust_identities` table.
|
| Address: only locality/region/country are known from the repo copy
| ("Nanjangud, Karnataka"). A street address is emitted only when
| TRUST_STREET_ADDRESS / TRUST_POSTAL_CODE are set — never invented.
*/
return [
    'email' => env('TRUST_EMAIL', 'sriramguruji@vsrsms.in'),
    'phone' => env('TRUST_PHONE', '+91 98441 32318'),
    'instagram_url' => env('TRUST_INSTAGRAM_URL', 'https://www.instagram.com/vishwagurushishyavrundham.in/'),
    'founder' => env('TRUST_FOUNDER', 'Sri Ram Ram Das Guruji'),
    'founding_date' => env('TRUST_FOUNDING_DATE', '2007'),
    'description' => env(
        'TRUST_DESCRIPTION',
        'A charitable trust led by Sri Ram Ram Das Guruji. Since 2007 the trust has schooled children in need of care — including blind and deaf pupils receiving free education — with daily annadanam near Nanjangud, Mysore.'
    ),
    'address' => [
        'street' => env('TRUST_STREET_ADDRESS', ''),
        'locality' => env('TRUST_ADDRESS_LOCALITY', 'Nanjangud'),
        'region' => env('TRUST_ADDRESS_REGION', 'Karnataka'),
        'postal_code' => env('TRUST_POSTAL_CODE', ''),
        'country' => env('TRUST_ADDRESS_COUNTRY', 'IN'),
    ],
    // Used as og:image when a page supplies no share image.
    'default_share_image' => env('TRUST_DEFAULT_SHARE_IMAGE', '/icon-512.png'),
    // Fallback site name when APP_NAME is empty, so <title> is never blank.
    'fallback_name' => 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam',
];
