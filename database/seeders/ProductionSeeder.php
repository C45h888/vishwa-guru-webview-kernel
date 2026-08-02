<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Persistence\Contracts\PersistenceAdapterContract;
use Illuminate\Database\Seeder;

/**
 * ProductionSeeder — idempotent canonical seed for Neon prod.
 *
 * Seeds the two lookup tables that every other domain FKs into:
 *   - currencies     (ISO 4217 registry)
 *   - payment_providers (Razorpay primary, PayPal secondary)
 *   - campaigns      (1 active sample campaign so the public donation
 *                     form has a target on day one)
 *
 * Doctrine alignment:
 *   - Idempotent: every INSERT uses ON CONFLICT … DO UPDATE so the
 *     seeder is safe to re-run (idempotent doctrine per AGENTS.md).
 *   - Pure data: no schema modifications. Schema lives in
 *     schema-neon/V1-schema.sql + Laravel migrations.
 *   - Adapter-only: every write goes through PersistenceAdapterContract,
 *     never the DB:: facade. Doctrine: repository boundary.
 *
 * Doctrine source: schema-neon/seed-reference.sql. This seeder
 * produces equivalent state but uses the doctrine-correct
 * PersistenceAdapterContract path instead of raw psql.
 *
 * Usage:
 *   docker exec temple-trust-worker php artisan db:seed \
 *     --class=Database\\Seeders\\ProductionSeeder --force
 */
final class ProductionSeeder extends Seeder
{
    /** ISO 4217 registry — 9 currencies covering the project's expected footprint. */
    private const CURRENCIES = [
        ['code' => 'INR', 'name' => 'Indian Rupee',         'symbol' => '₹',  'minor_unit_digits' => 2, 'display_order' => 10],
        ['code' => 'USD', 'name' => 'United States Dollar', 'symbol' => '$',  'minor_unit_digits' => 2, 'display_order' => 20],
        ['code' => 'EUR', 'name' => 'Euro',                 'symbol' => '€',  'minor_unit_digits' => 2, 'display_order' => 30],
        ['code' => 'GBP', 'name' => 'Pound Sterling',       'symbol' => '£',  'minor_unit_digits' => 2, 'display_order' => 40],
        ['code' => 'AUD', 'name' => 'Australian Dollar',    'symbol' => 'A$', 'minor_unit_digits' => 2, 'display_order' => 50],
        ['code' => 'CAD', 'name' => 'Canadian Dollar',      'symbol' => 'C$', 'minor_unit_digits' => 2, 'display_order' => 60],
        ['code' => 'SGD', 'name' => 'Singapore Dollar',     'symbol' => 'S$', 'minor_unit_digits' => 2, 'display_order' => 70],
        ['code' => 'AED', 'name' => 'UAE Dirham',           'symbol' => 'د.إ', 'minor_unit_digits' => 2, 'display_order' => 80],
        ['code' => 'JPY', 'name' => 'Japanese Yen',         'symbol' => '¥',  'minor_unit_digits' => 0, 'display_order' => 90],
    ];

    /**
     * Payment gateway registry. Active providers are visible to the public
     * donation form; inactive providers are configured but hidden (future use).
     * priority: lower = preferred (Razorpay first for INR, PayPal second).
     */
    private const PAYMENT_PROVIDERS = [
        [
            'code' => 'razorpay', 'display_name' => 'Razorpay', 'is_active' => true,
            'priority' => 10,
            'supported_currencies' => ['INR'],
            'min_amount_minor' => 100, 'max_amount_minor' => 1_000_000_000,
        ],
        [
            'code' => 'paypal', 'display_name' => 'PayPal', 'is_active' => true,
            'priority' => 20,
            'supported_currencies' => ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD', 'AED', 'JPY'],
            'min_amount_minor' => 100, 'max_amount_minor' => 100_000_000,
        ],
        [
            'code' => 'cashfree', 'display_name' => 'Cashfree', 'is_active' => false,
            'priority' => 30,
            'supported_currencies' => ['INR'],
            'min_amount_minor' => 100, 'max_amount_minor' => 500_000_000,
        ],
        [
            'code' => 'binance', 'display_name' => 'Binance Pay', 'is_active' => false,
            'priority' => 40,
            'supported_currencies' => ['USD', 'EUR', 'GBP'],
            'min_amount_minor' => 100, 'max_amount_minor' => 1_000_000_000,
        ],
        [
            'code' => 'coinbase', 'display_name' => 'Coinbase Commerce', 'is_active' => false,
            'priority' => 50,
            'supported_currencies' => ['USD', 'EUR', 'GBP'],
            'min_amount_minor' => 100, 'max_amount_minor' => 1_000_000_000,
        ],
        [
            'code' => 'nowpayments', 'display_name' => 'NOWPayments', 'is_active' => false,
            'priority' => 60,
            'supported_currencies' => ['USD', 'EUR'],
            'min_amount_minor' => 100, 'max_amount_minor' => 1_000_000_000,
        ],
        [
            'code' => 'triplea', 'display_name' => 'TripleA', 'is_active' => false,
            'priority' => 70,
            'supported_currencies' => ['USD', 'SGD', 'JPY'],
            'min_amount_minor' => 100, 'max_amount_minor' => 1_000_000_000,
        ],
    ];

    /** One sample campaign so the public donation form has a target on day one. */
    private const SAMPLE_CAMPAIGN = [
        'id' => 'campaign_general_fund_2026',
        'slug' => 'temple-general-fund',
        'title' => 'Temple General Fund',
        'description' => 'Supports daily temple operations, priest honoraria, and facility maintenance. Donations to this fund are unrestricted and applied where most needed.',
        'short_description' => 'Daily operations and maintenance',
        'category' => 'general',
        'state' => 'active',
        'currency_code' => 'INR',
        'target_amount_minor' => 5_000_000, // ₹50,000
        'is_featured' => true,
        'display_order' => 1,
        'starts_at_offset_days' => -30,
        'ends_at_offset_days' => 365,
    ];

    /**
     * Canonical About-page content. Mirrors the JS-side
     * `resources/js/domains/cms/about-fallbacks.ts` defaults so the page
     * renders with real, opinionated content the moment the row lands.
     *
     * The page-level `body` and `body_html` are intentionally empty in
     * V1 — the structured sections (values / timeline / trustees / CTA)
     * are the only content on the About page. Prose may be added in V2
     * by populating `body_json` + re-running the body renderer.
     */
    private const ABOUT_PAGE = [
        // Stable entity id: <entity_type>_<26-char canonical ULID>.
        // Stable across reseeds so the ON CONFLICT (slug) path always
        // matches and the id never changes underneath the renderer.
        'id' => 'static_page_0001N6CAYNHGKPWRF7GDZ074RA',
        'slug' => 'about',
        'title' => 'About the Trust',
        'meta_description' => 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam is a registered charitable trust preserving the sacred rhythms of South Indian temple life through daily pooja, annadanam, and the care of the temple structure.',
        'is_homepage' => false,
        'display_order' => 10,
        'about_page_content' => [
            'version' => 1,
            'values' => [
                'eyebrow' => 'Our Values',
                'title' => 'Seva, Satya, and Smriti',
                'body' => 'The trust is sustained by three commitments: seva — the discipline of self-giving service; satya — clear, honest stewardship of every offering; and smriti — the careful preservation of the rhythms, language, and practices that make a temple a living tradition. We hold ourselves to these not as aspirations but as the operating doctrine of every decision the trust makes.',
                'image_file_id' => null,
                'alt_text' => null,
            ],
            'timeline' => [
                [
                    'year' => 1998,
                    'title' => 'Temple founded',
                    'description' => 'A small group of devotees established the temple on land donated by the founding family, with daily pooja as the anchor of every other activity to follow.',
                ],
                [
                    'year' => 2005,
                    'title' => 'Annadanam hall opens',
                    'description' => 'A dedicated hall was added to serve the community meal every day of the year, regardless of festival or quiet season. Annadanam has been offered without interruption since.',
                ],
                [
                    'year' => 2012,
                    'title' => 'Priest training program',
                    'description' => 'The trust began an in-house training program for temple priests, ensuring that the next generation of ritualists learn the full agamic discipline rather than a simplified subset.',
                ],
                [
                    'year' => 2019,
                    'title' => 'Temple structure restoration',
                    'description' => 'A multi-year restoration of the vimana and outer prakara was completed using traditional materials and craftspeople, with funding drawn entirely from devotee offerings.',
                ],
                [
                    'year' => 2024,
                    'title' => 'Online donations and receipts',
                    'description' => 'The trust launched its public digital platform so devotees anywhere in the world can offer seva and receive an official receipt and the temple\'s gratitude.',
                ],
            ],
            'trustees' => [
                [
                    'name' => 'Dr. Anjali Rao',
                    'role' => 'Chair, Board of Trustees',
                    'photo_file_id' => null,
                    'bio' => 'A Sanskrit scholar and practising devotee, Anjali has served on the board since 2014 and has chaired it since 2020. She guides the trust\'s academic and ritual standards.',
                ],
                [
                    'name' => 'Sundaram Iyer',
                    'role' => 'Treasurer',
                    'photo_file_id' => null,
                    'bio' => 'A retired banker, Sundaram has overseen the trust\'s finances for over a decade and is the principal author of the annual audit and donor receipts process.',
                ],
                [
                    'name' => 'Lakshmi Narayanan',
                    'role' => 'Trustee, Annadanam',
                    'photo_file_id' => null,
                    'bio' => 'Lakshmi leads the daily Annadanam programme and coordinates the volunteers who prepare and serve the community meal each day of the year.',
                ],
            ],
            'donate_cta' => [
                'eyebrow' => 'Offer Your Seva',
                'title' => 'Help sustain the temple\'s daily work',
                'body' => 'Every offering supports daily pooja, Annadanam, and the care of this sacred place. Contributions of any size are received with gratitude and acknowledged with a receipt.',
                'cta_label' => 'Donate Now',
                'cta_url' => '/donate',
            ],
        ],
    ];

    public function run(): void
    {
        $adapter = $this->container->make(PersistenceAdapterContract::class);

        $this->command->info('ProductionSeeder: starting idempotent seed against Neon prod.');

        $this->seedCurrencies($adapter);
        $this->seedPaymentProviders($adapter);
        $this->seedSampleCampaign($adapter);
        $this->seedAboutPage($adapter);

        $this->command->info('ProductionSeeder: complete.');
    }

    private function seedCurrencies(PersistenceAdapterContract $adapter): void
    {
        $this->command->info('  → currencies');
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);

        foreach (self::CURRENCIES as $c) {
            $r = $adapter->execute(
                "INSERT INTO currencies
                    (code, name, symbol, minor_unit_digits, display_order,
                     is_active, created_at, updated_at)
                 VALUES
                    (:code, :name, :symbol, :minor, :display_order,
                     TRUE, :now, :now)
                 ON CONFLICT (code) DO UPDATE SET
                    name              = EXCLUDED.name,
                    symbol            = EXCLUDED.symbol,
                    minor_unit_digits = EXCLUDED.minor_unit_digits,
                    display_order     = EXCLUDED.display_order,
                    updated_at        = EXCLUDED.updated_at",
                [
                    'code'          => $c['code'],
                    'name'          => $c['name'],
                    'symbol'        => $c['symbol'],
                    'minor'         => $c['minor_unit_digits'],
                    'display_order' => $c['display_order'],
                    'now'           => $now,
                ]
            );
            if ($r->isFailure()) {
                $this->command->error("    ! currency {$c['code']}: ".$r->error());
            }
        }
    }

    private function seedPaymentProviders(PersistenceAdapterContract $adapter): void
    {
        $this->command->info('  → payment_providers');
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);

        foreach (self::PAYMENT_PROVIDERS as $p) {
            // Build the PostgreSQL CHAR(3)[] literal from a PHP array.
            $quoted = [];
            foreach ($p['supported_currencies'] as $code) {
                $quoted[] = '"'.addslashes($code).'"';
            }
            $currencyArr = '{'.implode(',', $quoted).'}';

            $r = $adapter->execute(
                "INSERT INTO payment_providers
                    (code, display_name, is_active, priority, supported_currencies,
                     min_amount_minor, max_amount_minor, configuration,
                     created_at, updated_at)
                 VALUES
                    (:code, :name, :active, :priority, :currencies::CHAR(3)[],
                     :min, :max, '{}'::jsonb,
                     :now, :now)
                 ON CONFLICT (code) DO UPDATE SET
                    display_name         = EXCLUDED.display_name,
                    is_active            = EXCLUDED.is_active,
                    priority             = EXCLUDED.priority,
                    supported_currencies = EXCLUDED.supported_currencies,
                    min_amount_minor     = EXCLUDED.min_amount_minor,
                    max_amount_minor     = EXCLUDED.max_amount_minor,
                    updated_at           = EXCLUDED.updated_at",
                [
                    'code'        => $p['code'],
                    'name'        => $p['display_name'],
                    'active'      => $p['is_active'] ? 'true' : 'false',
                    'priority'    => $p['priority'],
                    'currencies'  => $currencyArr,
                    'min'         => $p['min_amount_minor'],
                    'max'         => $p['max_amount_minor'],
                    'now'         => $now,
                ]
            );
            if ($r->isFailure()) {
                $this->command->error("    ! payment_provider {$p['code']}: ".$r->error());
            }
        }
    }

    private function seedSampleCampaign(PersistenceAdapterContract $adapter): void
    {
        $this->command->info('  → campaigns (sample)');
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);

        $c = self::SAMPLE_CAMPAIGN;
        $r = $adapter->execute(
            "INSERT INTO campaigns (
                id, slug, title, description, short_description, category,
                state, currency_code, target_amount_minor,
                is_featured, display_order, starts_at, ends_at,
                metadata, created_at, updated_at
             ) VALUES (
                :id, :slug, :title, :description, :short, :category,
                :state, :currency_code, :target,
                :featured, :display_order,
                NOW() + (:start_days || ' days')::interval,
                NOW() + (:end_days   || ' days')::interval,
                '{}'::jsonb, :now, :now
             )
             ON CONFLICT (id) DO UPDATE SET
                title              = EXCLUDED.title,
                description        = EXCLUDED.description,
                short_description  = EXCLUDED.short_description,
                category           = EXCLUDED.category,
                state              = EXCLUDED.state,
                target_amount_minor= EXCLUDED.target_amount_minor,
                is_featured        = EXCLUDED.is_featured,
                display_order      = EXCLUDED.display_order,
                starts_at          = EXCLUDED.starts_at,
                ends_at            = EXCLUDED.ends_at,
                updated_at         = EXCLUDED.updated_at",
            [
                'id'              => $c['id'],
                'slug'            => $c['slug'],
                'title'           => $c['title'],
                'description'     => $c['description'],
                'short'           => $c['short_description'],
                'category'        => $c['category'],
                'state'           => $c['state'],
                'currency_code'   => $c['currency_code'],
                'target'          => $c['target_amount_minor'],
                'featured'        => $c['is_featured'] ? 'true' : 'false',
                'display_order'   => $c['display_order'],
                'start_days'      => (string) $c['starts_at_offset_days'],
                'end_days'        => (string) $c['ends_at_offset_days'],
                'now'             => $now,
            ]
        );
        if ($r->isFailure()) {
            $this->command->error('    ! sample campaign: '.$r->error());
        }
    }

    /**
     * Idempotent seed of the /about static page with structured
     * `about_page_content` JSONB. Mirrors the JS-side
     * `about-fallbacks.ts` defaults so the page is fully rendered the
     * moment the row lands.
     */
    private function seedAboutPage(PersistenceAdapterContract $adapter): void
    {
        $this->command->info('  → static_pages (about)');
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);

        $a = self::ABOUT_PAGE;
        $aboutContentJson = json_encode(
            $a['about_page_content'],
            JSON_THROW_ON_ERROR,
        );

        $bodyJson = json_encode(
            ['version' => 1, 'blocks' => []],
            JSON_THROW_ON_ERROR,
        );
        $seoJson = json_encode(
            [
                'metaTitle' => null,
                'metaDescription' => $a['meta_description'],
                'canonicalUrl' => null,
                'ogImageFileId' => null,
                'keywords' => [],
            ],
            JSON_THROW_ON_ERROR,
        );

        $r = $adapter->execute(
            "INSERT INTO static_pages (
                id, slug, title, meta_description,
                body_json, body_html, seo_metadata,
                homepage_content, about_page_content,
                state, is_homepage, display_order,
                published_at, last_published_at,
                created_at, updated_at, created_by, updated_by
             ) VALUES (
                :id, :slug, :title, :meta_description,
                :body_json, :body_html, :seo_metadata,
                NULL, :about_page_content,
                'published', :is_homepage, :display_order,
                :now, :now,
                :now, :now, 'system', 'system'
             )
             ON CONFLICT (slug) WHERE deleted_at IS NULL DO UPDATE SET
                title              = EXCLUDED.title,
                meta_description   = EXCLUDED.meta_description,
                body_html          = EXCLUDED.body_html,
                seo_metadata       = EXCLUDED.seo_metadata,
                about_page_content = EXCLUDED.about_page_content,
                state              = EXCLUDED.state,
                is_homepage        = EXCLUDED.is_homepage,
                display_order      = EXCLUDED.display_order,
                published_at       = EXCLUDED.published_at,
                last_published_at  = EXCLUDED.last_published_at,
                updated_at         = EXCLUDED.updated_at,
                updated_by         = EXCLUDED.updated_by",
            [
                'id'                 => $a['id'],
                'slug'               => $a['slug'],
                'title'              => $a['title'],
                'meta_description'   => $a['meta_description'],
                'body_json'          => $bodyJson,
                'body_html'          => '',
                'seo_metadata'       => $seoJson,
                'about_page_content' => $aboutContentJson,
                'is_homepage'        => $a['is_homepage'] ? 'true' : 'false',
                'display_order'      => $a['display_order'],
                'now'                => $now,
            ]
        );
        if ($r->isFailure()) {
            $this->command->error('    ! about page: '.$r->error());
        }
    }
}
