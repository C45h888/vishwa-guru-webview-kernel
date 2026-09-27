<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Campaigns-page editorial pillars
|--------------------------------------------------------------------------
|
| The campaigns page renders three large "What the campaigns sustain"
| pillar cards above the campaign grid. The card copy is static Svelte
| editorial content; the images are public-media assets owned by the Cms
| kernel and served through `/media/{id}` (canonical files under
| `storage/app/public/cms-media-upscaled/canonical/`).
|
| This config is the single source of truth shared by:
|   - `align_campaign_images.php`  (seeds `file_assets` + `cms_media_assets`)
|   - `Public\Campaigns\IndexController` (hydrates the `pillarMedia` payload)
|
| The ids are fixed so seeding is idempotent and the controller and seeder
| cannot drift. Order here defines pillar-card order on the page.
|
*/

return [
    'donation_pool_campaign_id' => 'campaign_general_fund_2026',
    'pillars' => [
        [
            'file_asset_id' => 'file_asset_0001NBFPX8R6Y1RXCSXZM4DSVM',
            'cms_media_id' => 'cms_media_0001NBFPX8KMQA9PBQAP5Z8WE9',
            'canonical' => 'kids-event.png',
            'alt' => 'Daily annadanam at the schools',
        ],
        [
            'file_asset_id' => 'file_asset_0001NBFPX8MW78BMRBV0TDDV4X',
            'cms_media_id' => 'cms_media_0001NBFPX8ZDRSPGMF1KP3XKXW',
            'canonical' => 'LAND.jpg',
            'alt' => 'Open land near the proposed campus site',
        ],
        [
            'file_asset_id' => 'file_asset_0001NBFPX8BZHYZQ35Q9QYPPNJ',
            'cms_media_id' => 'cms_media_0001NBFPX8J195D0A1BMVBGKCB',
            'canonical' => 'GAUSHALA.jpg',
            'alt' => 'Illustrative concept for a future Gaushala and temple campus',
        ],
    ],
];
