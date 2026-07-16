-- =====================================================================
-- Temple Trust Management System — Canonical Database Schema
-- Target:  Neon PostgreSQL 16+
-- Source:  Constitutional doctrine files (architecture.md, modules.md,
--          domain-model.md, payment-architecture.md, security.md)
-- Style:   snake_case, plural table names, ULID primary keys
-- =====================================================================
--
-- Identifier strategy:
--   Every primary key is a TEXT ULID with a type prefix
--   (e.g. 'donation_01HXYZ...'), matching the Persistence module's
--   EntityId value object. No SERIAL/BIGINT keys.
--
-- Money strategy:
--   All monetary amounts are stored as BIGINT in MINOR units
--   (paise for INR, cents for USD, etc.) with a CHAR(3) ISO 4217
--   currency code. Matches the Money value object.
--
-- Time strategy:
--   All timestamps are TIMESTAMPTZ in UTC. Application layer is
--   responsible for local-time display.
--
-- Soft delete:
--   Every domain table has a `deleted_at TIMESTAMPTZ NULL` column.
--   Hard deletes are reserved for GDPR/PII purge only.
--
-- Audit:
--   Every domain table has `created_at`, `updated_at`, and an
--   optional `created_by` / `updated_by` TEXT column. The cross-
--   cutting `audit_events` table captures fine-grained event log.
--
-- =====================================================================

-- ---------------------------------------------------------------------
-- 0. EXTENSIONS
-- ---------------------------------------------------------------------

CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS btree_gist;          -- enables EXCLUDE on static_pages.is_homepage (boolean has no native GiST opclass)

-- ---------------------------------------------------------------------
-- 1. ENUM TYPES  (must exist before any table that uses them)
-- ---------------------------------------------------------------------

-- Donation lifecycle (domain-model.md)
CREATE TYPE donation_state AS ENUM (
    'draft',
    'pending_payment',
    'payment_verified',
    'receipt_generated',
    'completed',
    'failed',
    'cancelled'
);

-- Payment lifecycle (module-inventory.md → TransactionStatus)
CREATE TYPE payment_status AS ENUM (
    'initialized',
    'pending',
    'authorized',
    'captured',
    'settling',
    'settled',
    'failed',
    'refunded',
    'partially_refunded',
    'disputed',
    'cancelled',
    'expired'
);

-- Campaign lifecycle
CREATE TYPE campaign_state AS ENUM (
    'draft',
    'active',
    'completed',
    'archived'
);

-- Static page lifecycle
CREATE TYPE static_page_state AS ENUM (
    'draft',
    'published',
    'updated',
    'archived'
);

-- Gallery lifecycle (shared by galleries + gallery_images)
CREATE TYPE gallery_state AS ENUM (
    'draft',
    'published',
    'archived'
);

-- Event lifecycle
CREATE TYPE event_state AS ENUM (
    'draft',
    'published',
    'completed',
    'archived'
);

-- Receipt lifecycle
CREATE TYPE receipt_state AS ENUM (
    'generated',
    'delivered',
    'archived'
);

-- Failure classification (payment-architecture.md)
CREATE TYPE failure_classification AS ENUM (
    'recoverable',
    'terminal'
);

-- Notification channels
CREATE TYPE notification_channel AS ENUM (
    'email',
    'sms',
    'whatsapp'
);

-- Notification delivery status
CREATE TYPE notification_status AS ENUM (
    'queued',
    'sent',
    'delivered',
    'failed',
    'bounced'
);

-- File asset owner categories (constrained polymorphic ownership)
CREATE TYPE file_owner_type AS ENUM (
    'donation',
    'receipt',
    'gallery_image',
    'event_banner',
    'hero_banner',
    'campaign_cover',
    'static_page_attachment',
    'donor_document'
);

-- Static page reference target categories
CREATE TYPE page_reference_type AS ENUM (
    'campaign',
    'gallery_image',
    'event'
);

-- Contact information categories
CREATE TYPE contact_type AS ENUM (
    'address',
    'phone',
    'email',
    'whatsapp',
    'social'
);

-- Audit actor categories (auth deferred)
CREATE TYPE audit_actor_type AS ENUM (
    'system',
    'admin',
    'donor',
    'gateway',
    'job'
);

-- ---------------------------------------------------------------------
-- 2. LOOKUP / REFERENCE TABLES
-- ---------------------------------------------------------------------

-- ISO 4217 currency registry -----------------------------------------
CREATE TABLE currencies (
    code               CHAR(3)        PRIMARY KEY,
    name               TEXT           NOT NULL,
    symbol             TEXT           NOT NULL,
    minor_unit_digits  SMALLINT       NOT NULL
                       CHECK (minor_unit_digits BETWEEN 0 AND 4),
    is_active          BOOLEAN        NOT NULL DEFAULT TRUE,
    display_order      INT            NOT NULL DEFAULT 0,
    created_at         TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMPTZ    NOT NULL DEFAULT NOW()
);
COMMENT ON TABLE currencies IS
    'ISO 4217 currency registry. Drives Money VO validation and UI formatting.';

-- Payment provider registry ------------------------------------------
CREATE TABLE payment_providers (
    code                  TEXT        PRIMARY KEY,
    display_name          TEXT        NOT NULL,
    is_active             BOOLEAN     NOT NULL DEFAULT TRUE,
    priority              INT         NOT NULL DEFAULT 100, -- lower = higher priority
    supported_currencies  CHAR(3)[]   NOT NULL DEFAULT '{}',
    min_amount_minor      BIGINT,
    max_amount_minor      BIGINT,
    configuration         JSONB       NOT NULL DEFAULT '{}'::jsonb,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT payment_providers_min_nonneg
        CHECK (min_amount_minor IS NULL OR min_amount_minor >= 0),
    CONSTRAINT payment_providers_range_valid
        CHECK (min_amount_minor IS NULL
               OR max_amount_minor IS NULL
               OR max_amount_minor >= min_amount_minor)
);
COMMENT ON TABLE payment_providers IS
    'Registry of supported payment gateways. Razorpay primary, PayPal secondary.';

-- ---------------------------------------------------------------------
-- 3. IDENTITY  (donors)
-- ---------------------------------------------------------------------

CREATE TABLE donors (
    id               TEXT          PRIMARY KEY,
    full_name        TEXT,
    email            CITEXT,
    phone            TEXT,                              -- E.164
    country_code     CHAR(2),                           -- ISO 3166-1 alpha-2
    address_line_1   TEXT,
    address_line_2   TEXT,
    city             TEXT,
    state_region     TEXT,
    postal_code      TEXT,
    pan_number       TEXT,                              -- Indian tax ID, app-level encrypt in V1.1
    preferred_lang   CHAR(5),                           -- BCP 47
    is_anonymized    BOOLEAN       NOT NULL DEFAULT FALSE,
    notes            TEXT,
    created_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    deleted_at       TIMESTAMPTZ,

    CONSTRAINT donors_has_contact_or_anonymous
        CHECK (is_anonymized = TRUE
               OR full_name IS NOT NULL
               OR email     IS NOT NULL
               OR phone     IS NOT NULL)
);
COMMENT ON TABLE donors IS
    'Donor PII. May be null-referenced by donations for anonymous giving. '
    'Auth deferred; no user-account linkage in V1.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 4. CMS DOMAIN
-- ---------------------------------------------------------------------

CREATE TABLE static_pages (
    id                  TEXT              PRIMARY KEY,
    slug                TEXT              NOT NULL,
    title               TEXT              NOT NULL,
    meta_description    TEXT,
    body_json           JSONB             NOT NULL DEFAULT '{}'::jsonb,
    body_html           TEXT,
    state               static_page_state NOT NULL DEFAULT 'draft',
    is_homepage         BOOLEAN           NOT NULL DEFAULT FALSE,
    display_order       INT               NOT NULL DEFAULT 0,
    seo_metadata        JSONB             NOT NULL DEFAULT '{}'::jsonb,
    published_at        TIMESTAMPTZ,
    last_published_at   TIMESTAMPTZ,
    created_at          TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    created_by          TEXT,
    updated_by          TEXT,

    -- Exactly one live homepage at a time
    CONSTRAINT static_pages_single_homepage
        EXCLUDE (is_homepage WITH =)
        WHERE (is_homepage = TRUE AND deleted_at IS NULL)
);
COMMENT ON TABLE static_pages IS
    'CMS-managed fixed pages. Slugs are part of the website structure and not arbitrarily creatable.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

-- Slug uniqueness is partial so admins can re-create a soft-deleted page
-- (or republish a draft) with the same URL.
/* CREATE PARTIAL INDEX stripped */

-- Hero banners --------------------------------------------------------
CREATE TABLE hero_banners (
    id                    TEXT              PRIMARY KEY,
    title                 TEXT,
    subtitle              TEXT,
    cta_label             TEXT,
    cta_url               TEXT,
    image_file_id         TEXT,
    mobile_image_file_id  TEXT,
    state                 static_page_state NOT NULL DEFAULT 'draft',
    display_order         INT               NOT NULL DEFAULT 0,
    starts_at             TIMESTAMPTZ,
    ends_at               TIMESTAMPTZ,
    created_at            TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    deleted_at            TIMESTAMPTZ,
    created_by            TEXT,
    updated_by            TEXT,

    CONSTRAINT hero_banners_dates_valid
        CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at >= starts_at)
);
COMMENT ON TABLE hero_banners IS
    'Reusable hero banner assets; linked to static pages via hero_banner_pages.';

-- Hero banner → static page (many-to-many) ---------------------------
CREATE TABLE hero_banner_pages (
    hero_banner_id   TEXT        NOT NULL,
    static_page_id   TEXT        NOT NULL,
    display_order    INT         NOT NULL DEFAULT 0,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    PRIMARY KEY (hero_banner_id, static_page_id),

    CONSTRAINT hero_banner_pages_banner_fk
        FOREIGN KEY (hero_banner_id) REFERENCES hero_banners(id)  ON DELETE CASCADE,
    CONSTRAINT hero_banner_pages_page_fk
        FOREIGN KEY (static_page_id)  REFERENCES static_pages(id) ON DELETE CASCADE
);
COMMENT ON TABLE hero_banner_pages IS
    'Junction: which static pages display which hero banners.';

-- Static page → campaign / gallery / event references ----------------
CREATE TABLE static_page_references (
    id              TEXT               PRIMARY KEY,
    static_page_id  TEXT               NOT NULL,
    reference_type  page_reference_type NOT NULL,
    reference_id    TEXT               NOT NULL,
    display_order   INT                NOT NULL DEFAULT 0,
    context         TEXT,
    created_at      TIMESTAMPTZ        NOT NULL DEFAULT NOW(),

    CONSTRAINT static_page_references_page_fk
        FOREIGN KEY (static_page_id) REFERENCES static_pages(id) ON DELETE CASCADE,

    UNIQUE (static_page_id, reference_type, reference_id, context)
);
COMMENT ON TABLE static_page_references IS
    'Polymorphic junction: static page → campaign/gallery_image/event.';

CREATE INDEX static_page_references_target_idx
    ON static_page_references (reference_type, reference_id);

-- Contact information ------------------------------------------------
CREATE TABLE contact_information (
    id              TEXT         PRIMARY KEY,
    label           TEXT         NOT NULL,
    contact_type    contact_type NOT NULL,
    value           TEXT         NOT NULL,
    is_primary      BOOLEAN      NOT NULL DEFAULT FALSE,
    display_order   INT          NOT NULL DEFAULT 0,
    metadata        JSONB        NOT NULL DEFAULT '{}'::jsonb,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    deleted_at      TIMESTAMPTZ
);
COMMENT ON TABLE contact_information IS
    'Public-facing contact points managed by CMS. Multiple per type; one primary per type enforced at app layer.';

-- ---------------------------------------------------------------------
-- 5. CAMPAIGNS  (donations-domain parent entity)
-- ---------------------------------------------------------------------

CREATE TABLE campaigns (
    id                  TEXT              PRIMARY KEY,
    slug                TEXT              NOT NULL,
    title               TEXT              NOT NULL,
    description         TEXT,
    short_description   TEXT,
    cover_image_file_id TEXT,
    category            TEXT              NOT NULL,
    target_amount_minor BIGINT,
    currency_code       CHAR(3)           NOT NULL,
    state               campaign_state    NOT NULL DEFAULT 'draft',
    starts_at           TIMESTAMPTZ,
    ends_at             TIMESTAMPTZ,
    display_order       INT               NOT NULL DEFAULT 0,
    is_featured         BOOLEAN           NOT NULL DEFAULT FALSE,
    metadata            JSONB             NOT NULL DEFAULT '{}'::jsonb,
    created_at          TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    created_by          TEXT,
    updated_by          TEXT,

    CONSTRAINT campaigns_target_positive
        CHECK (target_amount_minor IS NULL OR target_amount_minor > 0),
    CONSTRAINT campaigns_dates_valid
        CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at >= starts_at),
    CONSTRAINT campaigns_currency_fk
        FOREIGN KEY (currency_code) REFERENCES currencies(code)
);
COMMENT ON TABLE campaigns IS
    'Fundraising initiatives. Receives donations. Lifecycle: draft → active → completed → archived.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

-- Slug uniqueness is partial to allow re-creating a soft-deleted campaign with the same slug.
/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 6. DONATIONS
-- ---------------------------------------------------------------------

CREATE TABLE donations (
    id                      TEXT              PRIMARY KEY,
    campaign_id             TEXT              NOT NULL,
    donor_id                TEXT,

    donor_name_snapshot     TEXT,
    donor_email_snapshot    TEXT,
    donor_phone_snapshot    TEXT,
    donor_pan_snapshot      TEXT,
    donor_address_snapshot  JSONB,

    amount_minor            BIGINT            NOT NULL CHECK (amount_minor > 0),
    currency_code           CHAR(3)           NOT NULL,

    is_anonymous            BOOLEAN           NOT NULL DEFAULT FALSE,
    dedication              TEXT,
    donor_message           TEXT,
    internal_notes          TEXT,

    state                   donation_state    NOT NULL DEFAULT 'draft',

    submitted_at            TIMESTAMPTZ,
    payment_initiated_at    TIMESTAMPTZ,
    payment_verified_at     TIMESTAMPTZ,
    receipt_generated_at    TIMESTAMPTZ,
    completed_at            TIMESTAMPTZ,
    failed_at               TIMESTAMPTZ,
    cancelled_at            TIMESTAMPTZ,

    idempotency_key         TEXT,

    metadata                JSONB             NOT NULL DEFAULT '{}'::jsonb,

    created_at              TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    updated_at              TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    deleted_at              TIMESTAMPTZ,
    created_by              TEXT,
    updated_by              TEXT,

    CONSTRAINT donations_campaign_fk
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT,
    CONSTRAINT donations_donor_fk
        FOREIGN KEY (donor_id)    REFERENCES donors(id)    ON DELETE RESTRICT,
    CONSTRAINT donations_currency_fk
        FOREIGN KEY (currency_code) REFERENCES currencies(code),

    -- Anonymous donations must NOT carry identifying snapshots
    CONSTRAINT donations_anonymous_no_pii
        CHECK (is_anonymous = FALSE
               OR (donor_name_snapshot  IS NULL
                   AND donor_email_snapshot IS NULL
                   AND donor_phone_snapshot IS NULL
                   AND donor_pan_snapshot   IS NULL
                   AND donor_address_snapshot IS NULL)),

    -- Donation state ↔ timestamp correlation: a row in a specific state
    -- must have the matching lifecycle timestamp populated.
    CONSTRAINT donations_state_completed_ts
        CHECK (state <> 'completed'          OR completed_at        IS NOT NULL),
    CONSTRAINT donations_state_failed_ts
        CHECK (state <> 'failed'             OR failed_at           IS NOT NULL),
    CONSTRAINT donations_state_cancelled_ts
        CHECK (state <> 'cancelled'          OR cancelled_at        IS NOT NULL),
    CONSTRAINT donations_state_receipt_ts
        CHECK (state <> 'receipt_generated'  OR receipt_generated_at IS NOT NULL),
    CONSTRAINT donations_state_payment_verified_ts
        CHECK (state <> 'payment_verified'   OR payment_verified_at  IS NOT NULL)
);
COMMENT ON TABLE donations IS
    'Business intent of a patron to contribute. '
    'Independent from payment processing. May be anonymous.';

COMMENT ON COLUMN donations.donor_pan_snapshot IS
    'Plaintext snapshot of donors.pan_number captured at donation creation. '
    'Encryption deferred to V1.1 alongside donors.pan_number; same PII '
    'classification as donors.pan_number.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

-- Hot path: lookup donation by idempotency_key on payment retry.
/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 7. PAYMENTS
-- ---------------------------------------------------------------------

CREATE TABLE payments (
    id                          TEXT              PRIMARY KEY,
    donation_id                 TEXT              NOT NULL,
    provider_code               TEXT              NOT NULL,

    provider_order_id           TEXT,
    provider_payment_id         TEXT,
    provider_reference_id       TEXT,

    amount_minor                BIGINT            NOT NULL CHECK (amount_minor > 0),
    currency_code               CHAR(3)           NOT NULL,
    amount_captured_minor       BIGINT,
    amount_refunded_minor       BIGINT            NOT NULL DEFAULT 0,
    fee_minor                   BIGINT,
    tax_minor                   BIGINT,

    method                      TEXT,
    method_detail               JSONB             NOT NULL DEFAULT '{}'::jsonb,

    status                      payment_status    NOT NULL DEFAULT 'initialized',

    signature                   TEXT,
    signature_verified_at       TIMESTAMPTZ,
    verified_at                 TIMESTAMPTZ,
    verification_metadata       JSONB             NOT NULL DEFAULT '{}'::jsonb,

    initiated_at                TIMESTAMPTZ,
    authorized_at               TIMESTAMPTZ,
    captured_at                 TIMESTAMPTZ,
    settled_at                  TIMESTAMPTZ,
    failed_at                   TIMESTAMPTZ,
    refunded_at                 TIMESTAMPTZ,
    cancelled_at                TIMESTAMPTZ,
    expired_at                  TIMESTAMPTZ,

    last_failure_code           TEXT,
    last_failure_reason         TEXT,

    idempotency_key             TEXT,

    raw_provider_response       JSONB             NOT NULL DEFAULT '{}'::jsonb,

    created_at                  TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    updated_at                  TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    deleted_at                  TIMESTAMPTZ,

    CONSTRAINT payments_donation_fk
        FOREIGN KEY (donation_id)  REFERENCES donations(id) ON DELETE RESTRICT,
    CONSTRAINT payments_provider_fk
        FOREIGN KEY (provider_code) REFERENCES payment_providers(code),
    CONSTRAINT payments_currency_fk
        FOREIGN KEY (currency_code) REFERENCES currencies(code),

    CONSTRAINT payments_unique_per_donation UNIQUE (donation_id),

    CONSTRAINT payments_refund_not_exceed_capture
        CHECK (amount_captured_minor IS NULL
               OR amount_refunded_minor <= amount_captured_minor),

    -- Payment status ↔ timestamp / amount correlation.
    CONSTRAINT payments_status_authorized_ts
        CHECK (status NOT IN ('authorized','captured','settled') OR authorized_at IS NOT NULL),
    CONSTRAINT payments_status_settled_ts
        CHECK (status <> 'settled'             OR settled_at         IS NOT NULL),
    CONSTRAINT payments_status_captured_amt
        CHECK (status NOT IN ('captured','settled','refunded','partially_refunded')
               OR amount_captured_minor IS NOT NULL),
    CONSTRAINT payments_status_refunded_full
        CHECK (status <> 'refunded'
               OR amount_refunded_minor = amount_captured_minor),
    CONSTRAINT payments_status_partial_refund_bounds
        CHECK (status <> 'partially_refunded'
               OR (amount_refunded_minor > 0
                   AND amount_refunded_minor < amount_captured_minor)),
    CONSTRAINT payments_status_failed_ts
        CHECK (status <> 'failed'    OR failed_at    IS NOT NULL),
    CONSTRAINT payments_status_cancelled_ts
        CHECK (status <> 'cancelled' OR cancelled_at IS NOT NULL),
    CONSTRAINT payments_status_expired_ts
        CHECK (status <> 'expired'   OR expired_at   IS NOT NULL)
);
COMMENT ON TABLE payments IS
    'Verified financial transaction fulfilling a donation. '
    'Persisted only after gateway signature verification.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

-- Hot path: lookup payment by idempotency_key on retry.
/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 8. PAYMENT FAILURE STATES  (FailureStateManager)
-- ---------------------------------------------------------------------

CREATE TABLE failure_states (
    id                   TEXT                    PRIMARY KEY,
    payment_id           TEXT                    NOT NULL,
    classification       failure_classification  NOT NULL,
    failure_code         TEXT                    NOT NULL,
    failure_reason       TEXT,
    failure_metadata     JSONB                   NOT NULL DEFAULT '{}'::jsonb,

    first_failed_at      TIMESTAMPTZ             NOT NULL,
    last_failed_at       TIMESTAMPTZ             NOT NULL,
    retry_count          INT                     NOT NULL DEFAULT 0,
    next_retry_at        TIMESTAMPTZ,
    max_retries          INT                     NOT NULL DEFAULT 3,

    resolved_at          TIMESTAMPTZ,
    resolution_notes     TEXT,
    resolved_by          TEXT,

    created_at           TIMESTAMPTZ             NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ             NOT NULL DEFAULT NOW(),

    CONSTRAINT failure_states_payment_fk
        FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE RESTRICT,

    CONSTRAINT failure_states_payment_unique UNIQUE (payment_id),

    CONSTRAINT failure_states_retry_count_nonneg
        CHECK (retry_count >= 0),
    CONSTRAINT failure_states_retry_count_capped
        CHECK (retry_count <= max_retries)
);
COMMENT ON TABLE failure_states IS
    'Current failure state of a payment. Classified recoverable/terminal. '
    'Terminal failures require manual resolution.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 9. RECEIPTS
-- ---------------------------------------------------------------------

CREATE TABLE receipts (
    id                       TEXT           PRIMARY KEY,
    receipt_number           TEXT           NOT NULL UNIQUE,
    donation_id              TEXT           NOT NULL,
    payment_id               TEXT           NOT NULL,
    campaign_id              TEXT           NOT NULL,
    campaign_title_snapshot  TEXT           NOT NULL,

    donor_name               TEXT           NOT NULL,
    donor_email              TEXT,
    donor_pan                TEXT,
    donor_address            JSONB          NOT NULL DEFAULT '{}'::jsonb,

    amount_minor             BIGINT         NOT NULL CHECK (amount_minor > 0),
    currency_code            CHAR(3)        NOT NULL,
    amount_in_words          TEXT,

    is_tax_deductible        BOOLEAN        NOT NULL DEFAULT TRUE,
    tax_80g_eligible         BOOLEAN        NOT NULL DEFAULT FALSE,

    receipt_file_id          TEXT,
    certificate_80g_file_id  TEXT,
    certificate_80g_number   TEXT,

    state                    receipt_state  NOT NULL DEFAULT 'generated',
    generated_at             TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    delivered_at             TIMESTAMPTZ,
    delivery_channel         notification_channel,
    delivery_metadata        JSONB          NOT NULL DEFAULT '{}'::jsonb,

    content_hash             CHAR(64)       NOT NULL,

    created_at               TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    updated_at               TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    deleted_at               TIMESTAMPTZ,

    CONSTRAINT receipts_donation_fk
        FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE RESTRICT,
    CONSTRAINT receipts_payment_fk
        FOREIGN KEY (payment_id)  REFERENCES payments(id)  ON DELETE RESTRICT,
    CONSTRAINT receipts_campaign_fk
        FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE RESTRICT,
    CONSTRAINT receipts_currency_fk
        FOREIGN KEY (currency_code) REFERENCES currencies(code),

    -- receipt_file_id / certificate_80g_file_id FKs to file_assets are added
    -- after file_assets is created (see §12 deferred block) to keep CREATE
    -- order valid. They match the pattern used elsewhere in the schema.

    CONSTRAINT receipts_unique_per_donation UNIQUE (donation_id),
    CONSTRAINT receipts_unique_per_payment  UNIQUE (payment_id)
);
COMMENT ON TABLE receipts IS
    'Official acknowledgement of a verified financial contribution. '
    'Generated only after payment verification + DB persistence.';

COMMENT ON COLUMN receipts.donor_pan IS
    'Plaintext snapshot of donors.pan_number captured at receipt generation. '
    'Encryption deferred to V1.1 alongside donors.pan_number; same PII '
    'classification and retention rules apply.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 10. GALLERY
-- ---------------------------------------------------------------------

CREATE TABLE galleries (
    id                    TEXT           PRIMARY KEY,
    slug                  TEXT           NOT NULL,
    title                 TEXT           NOT NULL,
    description           TEXT,
    cover_image_file_id   TEXT,
    state                 gallery_state  NOT NULL DEFAULT 'draft',
    display_order         INT            NOT NULL DEFAULT 0,
    is_featured           BOOLEAN        NOT NULL DEFAULT FALSE,
    published_at          TIMESTAMPTZ,
    metadata              JSONB          NOT NULL DEFAULT '{}'::jsonb,
    created_at            TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    deleted_at            TIMESTAMPTZ,
    created_by            TEXT,
    updated_by            TEXT
);
COMMENT ON TABLE galleries IS
    'Top-level collection of visual media. Future: Albums layered on top.';

/* CREATE PARTIAL INDEX stripped */

-- Slug uniqueness is partial to allow re-creating a soft-deleted gallery with the same slug.
/* CREATE PARTIAL INDEX stripped */

CREATE TABLE gallery_images (
    id                    TEXT           PRIMARY KEY,
    gallery_id            TEXT           NOT NULL,
    file_asset_id         TEXT,
    title                 TEXT,
    caption               TEXT,
    alt_text              TEXT,
    photographer_credit   TEXT,
    taken_at              DATE,
    display_order         INT            NOT NULL DEFAULT 0,
    is_featured           BOOLEAN        NOT NULL DEFAULT FALSE,
    state                 gallery_state  NOT NULL DEFAULT 'draft',
    published_at          TIMESTAMPTZ,
    metadata              JSONB          NOT NULL DEFAULT '{}'::jsonb,
    created_at            TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    deleted_at            TIMESTAMPTZ,
    created_by            TEXT,
    updated_by            TEXT,

    CONSTRAINT gallery_images_gallery_fk
        FOREIGN KEY (gallery_id) REFERENCES galleries(id) ON DELETE RESTRICT
);
COMMENT ON TABLE gallery_images IS
    'Single image asset belonging to a gallery.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 11. EVENTS
-- ---------------------------------------------------------------------

CREATE TABLE events (
    id                  TEXT           PRIMARY KEY,
    slug                TEXT           NOT NULL,
    title               TEXT           NOT NULL,
    description         TEXT,
    short_description   TEXT,
    banner_file_id      TEXT,

    starts_at           TIMESTAMPTZ    NOT NULL,
    ends_at             TIMESTAMPTZ,
    timezone            TEXT           NOT NULL DEFAULT 'Asia/Kolkata',
    venue               TEXT,
    venue_address       TEXT,

    state               event_state    NOT NULL DEFAULT 'draft',
    published_at        TIMESTAMPTZ,
    completed_at        TIMESTAMPTZ,
    is_featured         BOOLEAN        NOT NULL DEFAULT FALSE,
    display_order       INT            NOT NULL DEFAULT 0,

    metadata            JSONB          NOT NULL DEFAULT '{}'::jsonb,
    created_at          TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ    NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    created_by          TEXT,
    updated_by          TEXT,

    CONSTRAINT events_dates_valid
        CHECK (ends_at IS NULL OR ends_at >= starts_at)
);
COMMENT ON TABLE events IS
    'Public-facing temple events. V1 is informational only; registrations come later.';

/* CREATE PARTIAL INDEX stripped */

-- NOTE: An earlier draft had `events_upcoming_idx ON events (starts_at) WHERE state='published' AND starts_at >= NOW() AND deleted_at IS NULL`.
-- Partial-index predicates must be IMMUTABLE; NOW() is STABLE and PG 16 rejects it.
-- "Upcoming published events" queries now filter on starts_at >= NOW() at query time,
-- which `events_state_starts_idx` above (state, starts_at) WHERE deleted_at IS NULL
-- already satisfies efficiently.

-- Slug uniqueness is partial to allow re-creating a soft-deleted event with the same slug.
/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 12. FILE ASSETS  (metadata only; binary lives in Laravel storage)
-- ---------------------------------------------------------------------

CREATE TABLE file_assets (
    id                  TEXT          PRIMARY KEY,
    owner_type          file_owner_type NOT NULL,
    owner_id            TEXT          NOT NULL,

    original_filename   TEXT          NOT NULL,
    storage_disk        TEXT          NOT NULL,
    storage_path        TEXT          NOT NULL,
    mime_type           TEXT          NOT NULL,
    file_size_bytes     BIGINT        NOT NULL CHECK (file_size_bytes > 0),
    file_hash_sha256    CHAR(64)      NOT NULL,

    purpose             TEXT,
    is_public           BOOLEAN       NOT NULL DEFAULT FALSE,
    is_archived         BOOLEAN       NOT NULL DEFAULT FALSE,
    archived_at         TIMESTAMPTZ,

    metadata            JSONB         NOT NULL DEFAULT '{}'::jsonb,
    uploaded_at         TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    created_at          TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    deleted_at          TIMESTAMPTZ,
    uploaded_by         TEXT
);
COMMENT ON TABLE file_assets IS
    'File metadata registry. Binary content lives in Laravel storage; DB only stores pointers + integrity hashes.';

/* CREATE PARTIAL INDEX stripped */

CREATE INDEX file_assets_hash_idx
    ON file_assets (file_hash_sha256);

-- Now add deferred FKs to file_assets (cycle-safe: created first, then referenced)
ALTER TABLE campaigns
    ADD CONSTRAINT campaigns_cover_image_fk
    FOREIGN KEY (cover_image_file_id) REFERENCES file_assets(id) ON DELETE SET NULL;

ALTER TABLE receipts
    ADD CONSTRAINT receipts_receipt_file_fk
    FOREIGN KEY (receipt_file_id) REFERENCES file_assets(id) ON DELETE RESTRICT;

ALTER TABLE receipts
    ADD CONSTRAINT receipts_80g_file_fk
    FOREIGN KEY (certificate_80g_file_id) REFERENCES file_assets(id) ON DELETE RESTRICT;

ALTER TABLE gallery_images
    ADD CONSTRAINT gallery_images_file_fk
    FOREIGN KEY (file_asset_id) REFERENCES file_assets(id) ON DELETE RESTRICT;

ALTER TABLE galleries
    ADD CONSTRAINT galleries_cover_image_fk
    FOREIGN KEY (cover_image_file_id) REFERENCES file_assets(id) ON DELETE SET NULL;

ALTER TABLE hero_banners
    ADD CONSTRAINT hero_banners_image_fk
    FOREIGN KEY (image_file_id) REFERENCES file_assets(id) ON DELETE RESTRICT;

ALTER TABLE hero_banners
    ADD CONSTRAINT hero_banners_mobile_image_fk
    FOREIGN KEY (mobile_image_file_id) REFERENCES file_assets(id) ON DELETE RESTRICT;

ALTER TABLE events
    ADD CONSTRAINT events_banner_fk
    FOREIGN KEY (banner_file_id) REFERENCES file_assets(id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 13. WEBHOOK EVENTS  (payment gateway idempotency)
-- ---------------------------------------------------------------------

CREATE TABLE webhook_events (
    id                    TEXT          PRIMARY KEY,
    provider_code         TEXT          NOT NULL,
    provider_event_id     TEXT          NOT NULL,
    event_type            TEXT          NOT NULL,
    payload               JSONB         NOT NULL,
    headers               JSONB         NOT NULL DEFAULT '{}'::jsonb,
    signature             TEXT,
    signature_verified    BOOLEAN       NOT NULL DEFAULT FALSE,
    related_payment_id    TEXT,

    received_at           TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    processed_at          TIMESTAMPTZ,
    processing_error      TEXT,
    retry_count           INT           NOT NULL DEFAULT 0,

    created_at            TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ   NOT NULL DEFAULT NOW(),

    CONSTRAINT webhook_events_provider_fk
        FOREIGN KEY (provider_code) REFERENCES payment_providers(code),
    CONSTRAINT webhook_events_payment_fk
        FOREIGN KEY (related_payment_id) REFERENCES payments(id) ON DELETE SET NULL,

    CONSTRAINT webhook_events_idempotent UNIQUE (provider_code, provider_event_id)
);
COMMENT ON TABLE webhook_events IS
    'Idempotent log of all payment-gateway webhook deliveries. '
    'Unique (provider_code, provider_event_id) is the cornerstone of payment idempotency.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 14. IDEMPOTENCY KEYS  (donation creation, payment init, etc.)
-- ---------------------------------------------------------------------

CREATE TABLE idempotency_keys (
    key                    TEXT          PRIMARY KEY,
    scope                  TEXT          NOT NULL,
    request_fingerprint    TEXT          NOT NULL,
    response_status        INT,
    response_body          JSONB,
    locked_by              TEXT,
    locked_at              TIMESTAMPTZ,
    created_at             TIMESTAMPTZ   NOT NULL DEFAULT NOW(),
    completed_at           TIMESTAMPTZ,
    expires_at             TIMESTAMPTZ   NOT NULL,

    CONSTRAINT idempotency_keys_expiry_after_create
        CHECK (expires_at > created_at)
);
COMMENT ON TABLE idempotency_keys IS
    'Generic idempotency table for non-webhook operations (donation create, payment init). '
    'Webhook idempotency lives in webhook_events.';

CREATE INDEX idempotency_keys_scope_expiry_idx
    ON idempotency_keys (scope, expires_at);

-- Now add the deferred FK from donations to idempotency_keys
ALTER TABLE donations
    ADD CONSTRAINT donations_idempotency_key_fk
    FOREIGN KEY (idempotency_key) REFERENCES idempotency_keys(key) ON DELETE SET NULL;

ALTER TABLE payments
    ADD CONSTRAINT payments_idempotency_key_fk
    FOREIGN KEY (idempotency_key) REFERENCES idempotency_keys(key) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- 15. NOTIFICATIONS
-- ---------------------------------------------------------------------

CREATE TABLE notifications (
    id                     TEXT                PRIMARY KEY,
    channel                notification_channel NOT NULL,
    recipient              TEXT                NOT NULL,
    subject                TEXT,
    body                   TEXT                NOT NULL,
    template_key           TEXT,
    related_entity_type    TEXT,
    related_entity_id      TEXT,
    status                 notification_status  NOT NULL DEFAULT 'queued',
    provider_message_id    TEXT,
    provider_response      JSONB               NOT NULL DEFAULT '{}'::jsonb,
    queued_at              TIMESTAMPTZ         NOT NULL DEFAULT NOW(),
    sent_at                TIMESTAMPTZ,
    delivered_at           TIMESTAMPTZ,
    failed_at              TIMESTAMPTZ,
    failure_reason         TEXT,
    retry_count            INT                 NOT NULL DEFAULT 0,
    max_retries            INT                 NOT NULL DEFAULT 3,
    next_retry_at          TIMESTAMPTZ,
    metadata               JSONB               NOT NULL DEFAULT '{}'::jsonb,
    created_at             TIMESTAMPTZ         NOT NULL DEFAULT NOW(),
    updated_at             TIMESTAMPTZ         NOT NULL DEFAULT NOW(),

    CONSTRAINT notifications_retry_count_nonneg
        CHECK (retry_count >= 0),
    CONSTRAINT notifications_retry_count_capped
        CHECK (retry_count <= max_retries)
);
COMMENT ON TABLE notifications IS
    'Outbound notification log. V1 = email; SMS/WhatsApp later. '
    'Channels routed through the Notification Service, not providers directly.';

/* CREATE PARTIAL INDEX stripped */

/* CREATE PARTIAL INDEX stripped */

CREATE INDEX notifications_related_entity_idx
    ON notifications (related_entity_type, related_entity_id);

-- ---------------------------------------------------------------------
-- 16. AUDIT EVENTS  (cross-cutting audit trail)
-- ---------------------------------------------------------------------

CREATE TABLE audit_events (
    id                TEXT              PRIMARY KEY,
    actor_type        audit_actor_type  NOT NULL,
    actor_id          TEXT,
    action            TEXT              NOT NULL,
    entity_type       TEXT              NOT NULL,
    entity_id         TEXT              NOT NULL,
    request_id        TEXT,
    ip_address        INET,
    user_agent        TEXT,
    occurred_at       TIMESTAMPTZ       NOT NULL DEFAULT NOW(),
    metadata          JSONB             NOT NULL DEFAULT '{}'::jsonb
);
COMMENT ON TABLE audit_events IS
    'Append-only audit log. Every security-sensitive operation should produce one row. '
    'Auth is deferred, so actor_type covers system/gateway/job as well as human admins.';

CREATE INDEX audit_events_entity_idx
    ON audit_events (entity_type, entity_id, occurred_at DESC);

/* CREATE PARTIAL INDEX stripped */

CREATE INDEX audit_events_action_recent_idx
    ON audit_events (action, occurred_at DESC);

/* CREATE PARTIAL INDEX stripped */

-- ---------------------------------------------------------------------
-- 17. IMMUTABILITY POLICY  (audit_events + receipts)
-- ---------------------------------------------------------------------
--
-- audit_events is append-only. Application code must never issue UPDATE or
-- DELETE statements on this table; every event is a single INSERT.
--
-- receipts: financial fields (donation_id, payment_id, campaign_id,
-- amount_minor, currency_code, donor_name, donor_pan, donor_address,
-- content_hash, receipt_number) are immutable once generated. State
-- transitions (generated → delivered → archived) and delivery_metadata
-- may UPDATE. Soft-delete via deleted_at is permitted and reserved for
-- compliance/GDPR purge scenarios.
--
-- Note: enforce this in the application service layer (Laravel policies),
-- not via Postgres triggers, to keep migration reversal simple.

-- =====================================================================
-- END OF SCHEMA
-- =====================================================================
