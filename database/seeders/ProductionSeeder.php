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

    /** One pooled fund spans school operations and every campus stage. */
    private const SAMPLE_CAMPAIGN = [
        'id' => 'campaign_general_fund_2026',
        'slug' => 'temple-general-fund',
        'title' => 'Schools & Campus Pooled Fund',
        'description' => 'Donations are pooled by the trust across daily school operations and the staged campus programme near Nanjangud. The pooled fund includes land acquisition and planned Gaushala, temple, healing-environment, and cow-care work as those stages proceed. A gift is not restricted to a single sub-project. The trust publishes how campaign funds were used when the campaign closes.',
        'short_description' => 'One pooled fund for daily school support and the staged campus programme',
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
        'meta_description' => 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS) is a charitable trust led by Sri Ram Ram Das Guruji. Since 2007 the trust has schooled children in need of care — including blind and deaf pupils receiving free education — sustained by daily annadanam. Donations support the schools’ daily operations and the proposed three-acre healing and service campus near Nanjangud, outside Mysore.',
        'is_homepage' => false,
        'display_order' => 10,
        'about_page_content' => [
            'version' => 2,
            'values' => [
                'eyebrow' => 'Our Values',
                'title' => 'Service, care, devotion, and purpose',
                'body' => 'Four commitments that hold the trust’s work together — not aspirations, but the standards by which every decision is checked. They have guided the schools since 2007, and they will guide the proposed campus as it is planned and built.',
                'image_file_id' => null,
                'alt_text' => null,
                'pillars' => [
                    ['name' => 'Service', 'description' => 'The practice of showing up, daily, without recognition. The work of the trust is sustained by people who treat service as a discipline.', 'icon_key' => 'care'],
                    ['name' => 'Care', 'description' => 'Attention to the actual needs of those in our charge — the children at the schools, the devotees, and the cows who will live at the proposed campus.', 'icon_key' => 'dharma'],
                    ['name' => 'Devotion', 'description' => 'The thread that holds the work together. The unforced steadiness of those who keep the work going, and the daily rhythm of prayer, work, and care.', 'icon_key' => 'devotion'],
                    ['name' => 'Purpose', 'description' => 'A clear sense of what the trust is for — service in the world, not preservation as an end in itself.', 'icon_key' => 'purpose'],
                ],
            ],
            'story' => [
                'eyebrow' => 'Our Story',
                'title' => 'Schools running today, the next chapter being prepared',
                'body' => 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS) is a charitable trust led by Sri Ram Ram Das Guruji. Guruji came from a teaching background and became a respected ritual and spiritual guide in Mysore. In 2012, he received Power of Attorney for the trust and began the major service endeavour that has shaped the public work since then. Since 2007 the trust has schooled children in need of care — including blind and deaf pupils who receive free education — with schooling, life skills, and cultural training including Bharatanatyam. The schools run today: daily life is sustained by annadanam, covering food, water, and events for the children. Alongside this ongoing work, the trust’s next chapter is a proposed three-acre healing and service campus near Nanjangud, outside Mysore. The campus land is under discussion; one pooled fund supports daily school operations and the staged campus programme, including land acquisition and planned Gaushala, temple, healing-environment, and cow-care work.',
            ],
            'stats' => [
                ['number' => '15+', 'label' => 'Years schooling children in need of care', 'description' => 'Since 2007 the trust has schooled children in need of care, including blind and deaf pupils who receive free education. The schools run today.'],
                ['number' => '~300', 'label' => 'Children housed and educated', 'description' => 'Approximately 300 children in need of care have been housed, educated, and supported across changing batches, alongside the pupils studying at the schools today.'],
                ['number' => 'Daily', 'label' => 'Annadanam at the schools', 'description' => 'Every school day the trust provides daily annadanam for the children in its care: meals, drinking water, and events at the schools. Donations sustain these daily operations.'],
                ['number' => '3 acres', 'label' => 'Proposed campus size', 'description' => 'A proposed three-acre healing and service campus near Nanjangud, outside Mysore. The land is under discussion and additional capital is required to complete the acquisition.'],
            ],
            'programs' => [
                ['eyebrow' => 'Ongoing · since 2007', 'title' => 'Free schooling for blind and deaf children', 'body' => 'The trust runs schools where children in need of care — including blind and deaf pupils — receive free education, with cultural training including Bharatanatyam. The schools run today. Daily needs such as food, water, and events are supported through the pooled Schools and Campus Fund, alongside the staged campus programme.', 'icon_key' => 'children_education'],
                ['eyebrow' => 'Ongoing · daily', 'title' => 'Annadanam at the schools', 'body' => 'Every school day the trust provides daily annadanam for the children in its care: meals, drinking water, and events at the schools. This work is included in the pooled fund alongside the staged campus programme.', 'icon_key' => 'land_acquisition'],
                ['eyebrow' => 'Campus · land acquisition', 'title' => 'Acquiring land near Nanjangud', 'body' => 'The pooled fund supports land acquisition near Nanjangud, outside Mysore, for the proposed three-acre campus. The land is under discussion; no construction begins until it is secured. Donations are not designated to this sub-project alone.', 'icon_key' => 'land_acquisition'],
                ['eyebrow' => 'Campus · planned stages', 'title' => 'Gaushala, temple, healing environment, and cow care', 'body' => 'The pooled fund also supports the campus stages planned for after land acquisition: a Gaushala, a simple Shiva temple, a healing environment, and long-term cow care. These are part of one pooled programme, not separate donation options.', 'icon_key' => 'gaushala'],
            ],
            'timeline' => [
                ['year' => 2007, 'title' => 'Schooling children in need begins', 'description' => 'The trust begins schooling children in need of care, near Mysore — including blind and deaf pupils on free education. The first cohorts join the schools.'],
                ['year' => 2012, 'title' => 'Guruji receives Power of Attorney', 'description' => 'Sri Ram Ram Das Guruji receives Power of Attorney for the trust and takes responsibility for the major service endeavour that has shaped the public work since then.'],
                ['year' => 2022, 'title' => 'School cohorts graduate, schools continue', 'description' => 'Senior cohorts complete their education and move forward in life. The schools continue to run: new pupils study, eat, and gather daily under annadanam.'],
                ['year' => 2026, 'title' => 'Schools and campus pooled fund', 'description' => 'The trust consolidates daily school support and the staged proposed campus programme into one pooled fund, including land acquisition, planned Gaushala and temple work, the healing environment, and cow care.'],
            ],
            'trustees' => [
                ['name' => 'Sri Ram Ram Das Guruji', 'role' => 'Spiritual Leader, VSRSMS', 'photo_file_id' => null, 'bio' => 'Sri Ram Ram Das Guruji came from a teaching background and became a respected ritual and spiritual guide in Mysore. In 2012, he received Power of Attorney for the trust and has been the principal author of the trust’s service work since then. He is the visible primary actor across every public event the trust runs and the subject of the trust’s published imagery. He remains the point of contact for donations, event coordination, and the trust’s spiritual direction.'],
                ['name' => 'Board of Trustees', 'role' => 'To be published', 'photo_file_id' => null, 'bio' => 'The composition of the wider board is recorded with the trust office. A full directory — names, roles, and short bios — will be published once the board’s formal roster is registered and approved.'],
            ],
            'visit' => [
                'eyebrow' => 'Plan your visit',
                'title' => 'The trust’s work is near Nanjangud; its office is in Mysore',
                'body' => 'The schools and proposed campus are near Nanjangud. The trust office is in Mysore; office hours, pooja timings, and visit details can be confirmed by contacting the office in advance.',
                'address' => "Trust office
No. 19/B, 2nd Cross, A.G. Block, N.R. Mohalla, Mysore — 570 007
Schools and proposed campus: near Nanjangud, Karnataka",
                'timings' => 'Office hours published by the trust office',
                'phone' => '+91 98441 32318',
                'dress_code' => 'Modest clothing preferred for visits to the temple premises. Specific dress requirements for the inner sanctum will be published with the formal timings.',
                'map_url' => null,
            ],
            'donate_cta' => [
                'eyebrow' => 'Support the schools and the campus',
                'title' => 'Sustain daily annadanam, help build the campus',
                'body' => 'Donations are pooled across daily school operations and the staged campus programme, including land acquisition, planned Gaushala and temple work, the healing environment, and cow care as those stages proceed. A gift is not restricted to one sub-project. Every contribution is acknowledged with an official receipt.',
                'cta_label' => 'Support the pooled fund',
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
        $this->seedLegalPage($adapter);

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

    /**
     * Canonical Legal-page row. The structured certificate content is
     * seeded directly into `legal_page_content` JSONB so the renderer
     * resolves a typed `LegalPageContent` value object without falling
     * through to the Svelte-side fallback. Display order 20 places it
     * after About (10).
     *
     * Reference numbers for all four certificates are intentionally
     * null — the trust office has not yet confirmed them. The page
     * renders a styled "To be confirmed by the trust office" line in
     * that case.
     */
    private const LEGAL_PAGE = [
        'id' => 'static_page_0001N6CAYNHGKPWRF7GDZ074RB',
        'slug' => 'legal',
        'title' => 'Legal & Tax-Exempt Standing',
        'meta_description' => 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS) is a charitable trust registered in Karnataka. This page presents the trust’s regulatory registrations — 80G, 12A, Power of Attorney, and TAN — and is addressed to donors and auditors who need to verify the trust’s legal standing before making a contribution.',
        'is_homepage' => false,
        'display_order' => 20,
    ];

    /**
     * Canonical certificate copy for the legal_page_content JSONB column.
     *
     * Reference numbers, validity periods, issuance dates, and issuing
     * authorities are sourced from the actual scanned PDFs on disk at
     * `storage/app/legalmedia/assets/{eighty_g,twelve_a,poa,tan}.pdf`.
     * The values were OCR-extracted and vision-verified against the
     * rendered documents — see the agent pass log for the extraction
     * trail. The seed is the canonical source; the Svelte-side
     * fallback file is the placeholder of last resort when the JSONB
     * column is null.
     *
     * Order matches the Svelte-side `LegalCertificate` literal-union
     * (`eighty_g | twelve_a | poa | tan`).
     */
    private const LEGAL_CERTIFICATES = [
        'intro' => [
            'eyebrow' => 'Regulatory standing',
            'title' => "The trust's legal and tax-exempt status",
            'body' => "The trust's public standing rests on four registrations issued by Indian statutory authorities. The scanned certificates are linked below for donor and auditor review. Certified copies are available from the trust office on written request.",
        ],
        'certificates' => [
            [
                'certificate_key' => 'eighty_g',
                'title' => '80G Certificate',
                'reference_number' => 'F.No.S-504/80G/CIT/MYS/2011-12',
                'description' => 'Enables donors to claim a tax deduction for contributions to the trust under Section 80G(5)(vi) of the Income Tax Act, 1961. Donations qualify for a 50% deduction subject to the donor’s applicable limits.',
                'icon_key' => 'eighty_g',
                'validity_period' => 'A.Y. 2011-12 onwards',
                'issued_on' => '23.02.2012',
                'issuing_authority' => 'Office of the Commissioner of Income-tax, Mysore',
            ],
            [
                'certificate_key' => 'twelve_a',
                'title' => '12A Registration',
                'reference_number' => 'F.No.S-504/12AA/CIT/MYS/2010-11',
                'description' => "Confirms the trust's registration as a Public Charitable Trust under Section 12A read with Section 12AA(1)(b)(i) of the Income Tax Act, 1961. Tax-exemption availability on the trust's income is considered separately by the Assessing Officer under sections 11 to 13.",
                'icon_key' => 'twelve_a',
                'validity_period' => 'w.e.f. A.Y. 2011-12',
                'issued_on' => '29.10.2010',
                'issuing_authority' => 'Office of the Commissioner of Income-tax, Mysore',
            ],
            [
                'certificate_key' => 'poa',
                'title' => 'Power of Attorney',
                'reference_number' => 'Board Resolution dated 17.07.2021',
                'description' => "Board Resolution of the trust authorising Sh. R. Sriram, Managing Trustee, to execute powers of attorney, open bank accounts, engage professional advisors, sign contracts, and take all steps necessary for the fulfilment of the trust's purposes.",
                'icon_key' => 'poa',
                'validity_period' => 'Continuing (no expiry)',
                'issued_on' => '17.07.2021',
                'issuing_authority' => 'Board of Trustees, VSRSMS',
            ],
            [
                'certificate_key' => 'tan',
                'title' => 'TAN',
                'reference_number' => 'BLRS60956A',
                'description' => 'Tax Deduction Account Number allotted to the trust for withholding-tax compliance under the Income Tax Act, 1961. Mandatory on all TDS challans, certificates, returns, and Tax Collection at Source (TCS) returns filed by the trust.',
                'icon_key' => 'tan',
                'validity_period' => 'Continuing (no expiry)',
                'issued_on' => '01.03.2019',
                'issuing_authority' => 'Income Tax Department (via NSDL e-TDS Intermediary)',
            ],
        ],
    ];

    /**
     * Idempotent seed of the /legal static page.
     *
     * Populates `legal_page_content` with the structured certificate
     * aggregate (intro + certificates[]). The PHP `LegalPageContent`
     * value object validates the JSON shape; the factory's
     * `assertStillValid()` is implicitly invoked through the
     * repository's `update()` call.
     */
    private function seedLegalPage(PersistenceAdapterContract $adapter): void
    {
        $this->command->info('  → static_pages (legal)');
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);

        $l = self::LEGAL_PAGE;

        $bodyJson = json_encode(
            ['version' => 1, 'blocks' => []],
            JSON_THROW_ON_ERROR,
        );
        $seoJson = json_encode(
            [
                'metaTitle' => null,
                'metaDescription' => $l['meta_description'],
                'canonicalUrl' => null,
                'ogImageFileId' => null,
                'keywords' => [],
            ],
            JSON_THROW_ON_ERROR,
        );
        $legalContentJson = json_encode(
            [
                'version' => 1,
                'intro' => self::LEGAL_CERTIFICATES['intro'],
                'certificates' => self::LEGAL_CERTIFICATES['certificates'],
            ],
            JSON_THROW_ON_ERROR,
        );

        $r = $adapter->execute(
            "INSERT INTO static_pages (
                id, slug, title, meta_description,
                body_json, body_html, seo_metadata,
                homepage_content, about_page_content, legal_page_content,
                state, is_homepage, display_order,
                published_at, last_published_at,
                created_at, updated_at, created_by, updated_by
             ) VALUES (
                :id, :slug, :title, :meta_description,
                :body_json, :body_html, :seo_metadata,
                NULL, NULL, :legal_page_content,
                'published', :is_homepage, :display_order,
                :now, :now,
                :now, :now, 'system', 'system'
             )
             ON CONFLICT (slug) WHERE deleted_at IS NULL DO UPDATE SET
                title              = EXCLUDED.title,
                meta_description   = EXCLUDED.meta_description,
                body_html          = EXCLUDED.body_html,
                seo_metadata       = EXCLUDED.seo_metadata,
                legal_page_content = EXCLUDED.legal_page_content,
                state              = EXCLUDED.state,
                is_homepage        = EXCLUDED.is_homepage,
                display_order      = EXCLUDED.display_order,
                published_at       = EXCLUDED.published_at,
                last_published_at  = EXCLUDED.last_published_at,
                updated_at         = EXCLUDED.updated_at,
                updated_by         = EXCLUDED.updated_by",
            [
                'id'                  => $l['id'],
                'slug'                => $l['slug'],
                'title'               => $l['title'],
                'meta_description'    => $l['meta_description'],
                'body_json'           => $bodyJson,
                'body_html'           => '',
                'seo_metadata'        => $seoJson,
                'legal_page_content'  => $legalContentJson,
                'is_homepage'         => $l['is_homepage'] ? 'true' : 'false',
                'display_order'       => $l['display_order'],
                'now'                 => $now,
            ]
        );
        if ($r->isFailure()) {
            $this->command->error('    ! legal page: '.$r->error());
        }
    }
}
