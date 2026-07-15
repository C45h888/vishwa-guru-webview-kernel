README.md
Temple Trust — Database Schema (Neon PostgreSQL)

This is the canonical, end-to-end DB schema for the Temple Trust Management System, derived directly from the constitutional doctrine files (architecture.md, modules.md, domain-model.md, payment-architecture.md, security.md, module-inventory.md).
Contents
File	Purpose
schema.sql	Canonical DDL. Apply to Neon with psql or via Laravel migrations.
seed-reference.sql	Idempotent seed for currencies and payment_providers.
README.md	This file — design rationale, ERD, conventions, Laravel notes.
How to apply on Neon
bash

# 1. Create a Neon project + database, copy the connection string.
# 2. Apply the schema
psql "$NEON_DATABASE_URL" -f schema.sql
# 3. Seed reference data
psql "$NEON_DATABASE_URL" -f seed-reference.sql

Inside Laravel, schema changes go through migrations (one migration per topical concern: create_currencies_table, create_donors_table, ...). This SQL is the source of truth that migrations implement.
Design principles (mapped to constitutional doctrine)
Principle	Source	How the schema enforces it
ULID identifiers	module-inventory.md → EntityId	Every PK is TEXT, type-prefixed ULID (e.g. donation_01HXYZ...). No SERIAL/BIGSERIAL anywhere.
Money is minor units + ISO 4217	module-inventory.md → Money VO	amount_minor BIGINT + currency_code CHAR(3) FK to currencies. minor_unit_digits is the authoritative decimal count per currency.
Verify before persist	security.md + payment-architecture.md	webhook_events table is the single source of truth for payment verification, with UNIQUE(provider_code, provider_event_id) enforcing webhook idempotency. payments.signature_verified_at records the cryptographic check.
Financial immutability	security.md	ON DELETE RESTRICT on every financial FK; receipts.content_hash (SHA-256) detects tampering; audit_events is append-only (enforced at app layer via Laravel policies).
Anonymous donations	domain-model.md → Donation	donations.is_anonymous BOOLEAN; CHECK constraint forbids PII snapshots when anonymous; donor_id is nullable.
Domain boundaries	modules.md	Tables are grouped by domain (donations, payments, receipts, cms, gallery, events, identity). Cross-domain FKs are intentional integrity edges, not coupling — services are still the only allowed collaborator.
File metadata only in DB	architecture.md → file storage strategy	file_assets table stores path/hash/mime/size only; binary content lives in Laravel storage (S3, R2, local, etc.).
Idempotency for non-webhook ops	payment-architecture.md	Generic idempotency_keys table for donation.create, payment.init, etc. (Webhooks use their own table.)
Failure State is first-class	payment-architecture.md	failure_states table with classification (recoverable/terminal), retry tracking, resolution metadata.
Single temple, no multi-tenancy	architecture.md → current scope	No tenant_id columns, no org-scoping anywhere.
Auth deferred	modules.md → Authentication (Deferred)	created_by/updated_by are nullable TEXT for now. audit_events.actor_type covers system/gateway/job/admin/donor. Will FK to users once auth ships.
Soft deletes	Laravel convention	Every domain table has deleted_at TIMESTAMPTZ NULL. Indexes are partial: WHERE deleted_at IS NULL.
Audit everything	architecture.md → audit philosophy	audit_events is cross-cutting, append-only, and indexed by (entity_type, entity_id, occurred_at DESC).
Money math safety	defensive	CHECK (amount_minor > 0), CHECK (amount_refunded_minor <= amount_captured_minor), no FLOAT/DOUBLE anywhere.
Entity-Relationship Diagram
Table reference
Lookup tables
Table	Purpose	Notes
currencies	ISO 4217 registry	Drives Money VO validation, UI formatting, FX.
payment_providers	Gateway registry	priority = preference order; supported_currencies is a CHAR(3)[].
Identity
Table	Purpose	Notes
donors	Donor PII	May be referenced by donations or referenced-as-anonymous.
CMS
Table	Purpose	Notes
static_pages	Fixed pages (home/about/contact/donate/certifications)	Slug is the canonical handle; EXCLUDE constraint guarantees one live homepage.
hero_banners	Reusable hero assets	Linked to pages via hero_banner_pages.
hero_banner_pages	Junction	(banner, page) pairs.
static_page_references	Polymorphic refs to campaigns/gallery/events	Controlled by page_reference_type enum.
contact_information	Public contact points	Multi-type (address/phone/email/whatsapp/social); primary flag per type enforced at app layer.
Donations
Table	Purpose	Notes
campaigns	Fundraising initiatives	state is the lifecycle; target_amount_minor is optional.
donations	Donor intent	Carries PII snapshots; is_anonymous toggles the strict CHECK.
Payments
Table	Purpose	Notes
payments	Verified financial transaction	1:1 with donations in V1. status is the canonical state.
failure_states	Current failure state per payment	classification ∈ {recoverable, terminal}; one row per payment.
webhook_events	Idempotent gateway webhook log	The cornerstone of payment idempotency.
Receipts
Table	Purpose	Notes
receipts	Official acknowledgements	Generated only after verified payment; content_hash is the integrity anchor. 80G certificate is a separate file ref.
Gallery
Table	Purpose	Notes
galleries	Top-level media collection	Future: Albums layered on top.
gallery_images	Single image in a gallery	FK to file_assets; alt-text is required for accessibility.
Events
Table	Purpose	Notes
events	Public temple events	V1 is informational only. Registrations, RSVPs, and volunteer slots are deferred.
Cross-cutting
Table	Purpose	Notes
file_assets	File metadata	Binary lives in storage; DB holds pointer + SHA-256.
idempotency_keys	Generic idempotency	For non-webhook operations.
notifications	Outbound notification log	Channel-agnostic; supports email/sms/whatsapp.
audit_events	Append-only audit log	Indexed for entity-history lookups.
Lifecycle state machines (canonical)
text

DONATION                PAYMENT                    CAMPAIGN
─────────               ───────                    ────────
draft                   initialized                draft
   │                       │                          │
pending_payment         pending                    active
   │                       │                          │
payment_verified        authorized                completed
   │                       │                          │
receipt_generated       captured                  archived
   │                       │
completed               settled
                        ──────
                        failed / refunded
                        partially_refunded
                        disputed / cancelled
                        expired

RECEIPT                 GALLERY/IMAGE             EVENT
───────                 ─────────────             ─────
generated               draft                    draft
   │                       │                       │
delivered               published                published
   │                       │                       │
archived                archived                 completed
                                                   │
                                                archived

Naming conventions
Object	Convention	Example
Table names	plural snake_case	donation_state_transitions
Column names	singular snake_case	campaign_id, is_anonymous
Primary key	id (TEXT ULID, type-prefixed)	donation_01HXYZ...
Foreign key column	<singular_referenced_table>_id	campaign_id, donor_id
Boolean columns	is_ or has_ prefix, no _flag suffix	is_anonymous, is_featured
Timestamp columns	past-tense verb _at	verified_at, published_at
ENUM types	singular snake_case	donation_state
JSONB payloads	metadata (free-form) or specific name	seo_metadata, verification_metadata
Indexes	<table>_<columns>_<modifier>_idx	donations_campaign_created_idx
PostgreSQL features used

    ENUM types — state machines, channels, classifications.
    JSONB — flexible metadata (always with a default '{}'::jsonb).
    TIMESTAMPTZ — UTC everywhere; app layer handles timezones.
    CHECK constraints — domain rules (positive amounts, valid date ranges, no PII when anonymous).
    EXCLUDE constraints — Postgres-native "exactly one homepage".
    CITEXT — case-insensitive donor emails.
    INET — IP addresses in audit_events.
    Partial indexes — WHERE deleted_at IS NULL on every domain index for hot-path performance.
    CHAR(3)[] — currency arrays on payment_providers.supported_currencies.
    ON DELETE RESTRICT — financial/audit immutability.
    ON DELETE SET NULL — non-critical references (cover image, banner).
    ON DELETE CASCADE — owned children (junction tables, page references).

Laravel migration mapping (suggested order)
text

1.  create_currencies_table
2.  create_payment_providers_table
3.  create_donors_table
4.  create_file_assets_table            (created early so others can FK to it)
5.  create_static_pages_table
6.  create_hero_banners_table           (+ FK to file_assets)
7.  create_hero_banner_pages_table
8.  create_static_page_references_table
9.  create_contact_information_table
10. create_campaigns_table              (+ FK to currencies, file_assets)
11. create_idempotency_keys_table
12. create_donations_table              (+ FK to campaigns, donors, currencies, idempotency_keys)
13. create_payments_table               (+ FK to donations, payment_providers, currencies, idempotency_keys)
14. create_failure_states_table         (+ FK to payments)
15. create_receipts_table               (+ FK to donations, payments, campaigns, currencies, file_assets)
16. create_galleries_table              (+ FK to file_assets)
17. create_gallery_images_table         (+ FK to galleries, file_assets)
18. create_events_table                 (+ FK to file_assets)
19. create_webhook_events_table         (+ FK to payment_providers, payments)
20. create_notifications_table
21. create_audit_events_table
22. seed_currencies_table
23. seed_payment_providers_table

    Note: in Laravel, the file_assets table is created earlier (step 4) so the deferred FKs in schema.sql (ALTER TABLE ... ADD CONSTRAINT) can be expressed as inline $table->foreign(...) calls in each owning table's migration. The end-state schema is identical.

Deferred / future

These were intentionally not added in V1 but the schema doesn't block them:
Concept	Where it will land	Trigger
refunds table	New table; FK to payments	First refund flow
disputes table	New table; FK to payments	First chargeback flow
recurring_donations	New table; FK to donations and donors	Recurring giving product
event_registrations	New table; FK to events and donors	Event RSVP feature
albums	New table; FK to galleries and gallery_images	Gallery V2
users / admins	New table; replace created_by TEXT with FKs	Authentication module
PII encryption at rest	Add pgcrypto-backed columns on donors	Compliance review
outbox table	New table; for transactional outbox pattern	When queue workers land
Verification checklist (apply locally first)

    psql "$NEON_DATABASE_URL" -f schema.sql exits 0
    psql "$NEON_DATABASE_URL" -f seed-reference.sql exits 0
    SELECT count(*) FROM currencies; returns 9
    SELECT count(*) FROM payment_providers WHERE is_active; returns 2
    \d donations shows all FKs (campaign_id, donor_id, currency_code, idempotency_key)
    \d payments shows UNIQUE (donation_id)
    \d webhook_events shows UNIQUE (provider_code, provider_event_id)
    \d receipts shows both UNIQUE (donation_id) and UNIQUE (payment_id)
    INSERT INTO donations (..., is_anonymous=TRUE, donor_name_snapshot='X') fails (CHECK constraint)

If all 9 boxes check, the schema is good to ship to Neon.
