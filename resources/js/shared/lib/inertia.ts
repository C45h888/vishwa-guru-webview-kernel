export interface AuthUser {
    id: string;
    name: string;
    email: string;
    /**
     * Phase 4: Admin Kernel — role tag. Single canonical value in Pass 1:
     * `'admin'`. Future role expansion is a model + middleware change,
     * not a UI change; this type accepts any string to keep the door open.
     */
    role: string;
}

export interface SharedPageProps {
    appName: string;
    appShortName: string;
    appUrl: string;
    authUser: AuthUser | null;
    /**
     * Razorpay environment: 'live' in production, 'test' in sandbox.
     * Surfaced so the webview can gate "Test mode" copy in the donate
     * flow without env access on the client.
     */
    razorpayMode: 'live' | 'test';
    /** Public trust identity from config/trust.php. */
    trust?: {
        email: string;
        instagramUrl: string;
    };
}

export interface PublicMediaProps {
    id: string;
    url: string;
    mime_type: string;
    file_size_bytes: number;
    content_hash: string;
    alt_text: string | null;
    width: number | null;
    height: number | null;
    caption: string | null;
    credit: string | null;
}

export type AppPageProps<TProps = Record<string, unknown>> = TProps & SharedPageProps;

export type PageComponentProps<TProps = Record<string, unknown>> = AppPageProps<TProps>;

/**
 * Campaign progress rollup row (CampaignProgressDTO::toArray()).
 * One row per currency_code; amounts never mix currencies on the wire.
 */
export interface CampaignProgressProps {
    campaign_id: string;
    currency_code: string;
    raised_amount_minor: number;
    donation_count: number;
    distinct_donor_count: number;
}

/**
 * Paged result envelope shared by CampaignPagedResultDTO,
 * EventPagedResultDTO, GalleryPagedResultDTO.
 */
export interface PagedResult<TItem> {
    items: TItem[];
    page: number;
    per_page: number;
    total: number;
    has_more: boolean;
}

export interface PaginationProps {
    page: number;
    per_page: number;
    total: number;
    has_more: boolean;
}

/**
 * Narrow shape of a campaign summary as it flows over Inertia.
 * Mirrors CampaignSummaryDTO::toArray() exactly (snake_case keys).
 */
export interface CampaignSummaryProps {
    id: string;
    slug: string;
    title: string;
    short_description: string | null;
    category: string;
    currency_code: string;
    target_amount_minor: number | null;
    is_featured: boolean;
    state: string;
    starts_at: string | null;
    ends_at: string | null;
    cover_image_file_id: string | null;
    cover_image?: PublicMediaProps | null;
}

/**
 * Detail shape — extends summary with description, displayOrder,
 * metadata, createdAt. See CampaignDetailDTO::toArray().
 */
export interface CampaignDetailProps extends CampaignSummaryProps {
    description: string | null;
    is_active: boolean;
    display_order: number;
    metadata: Record<string, unknown>;
    created_at: string;
}

/**
 * Narrow shape of an event summary as it flows over Inertia.
 * Mirrors EventSummaryDTO::toArray().
 */
export interface EventSummaryProps {
    id: string;
    slug: string;
    title: string;
    short_description: string | null;
    starts_at: string;
    ends_at: string | null;
    timezone: string;
    venue: string | null;
    venue_address: string | null;
    state: string;
    is_featured: boolean;
    is_upcoming: boolean;
    banner_file_id: string | null;
    banner_image?: PublicMediaProps | null;
}

/**
 * Narrow shape of a gallery summary as it flows over Inertia.
 * Mirrors GallerySummaryDTO::toArray().
 */
export interface GallerySummaryProps {
    id: string;
    slug: string;
    title: string;
    short_description: string | null;
    cover_image_file_id: string | null;
    cover_image?: PublicMediaProps | null;
    image_count: number;
    is_featured: boolean;
    published_at: string | null;
    state: string;
}

/**
 * Image inside a gallery detail (GalleryImageDTO::toArray()).
 */
export interface GalleryImageProps {
    id: string;
    gallery_id: string;
    file_asset_id: string | null;
    image?: PublicMediaProps | null;
    title: string | null;
    caption: string | null;
    alt_text: string | null;
    photographer_credit: string | null;
    taken_at: string | null;
    display_order: number;
    is_featured: boolean;
    published_at: string | null;
}

/**
 * Narrow shape of a DonationIntent as it would be posted to
 * POST /api/v1/razorpay/checkout. Mirrors DonationIntent + DonorIdentity shapes.
 */
export interface DonorIdentityProps {
    name: string | null;
    email: string | null;
    phone: string | null;
    pan: string | null;
    address: Record<string, string> | null;
    is_anonymous: boolean;
}

export interface DonationIntentProps {
    campaign_id: string;
    donor: DonorIdentityProps;
    amount_minor: number;
    currency: string;
    dedication: string | null;
    donor_message: string | null;
    idempotency_key: string | null;
    internal_notes?: string | null;
    metadata?: Record<string, unknown>;
}

/**
 * What RazorpayCheckoutController returns on 201 success.
 */
export interface PaymentResultProps {
    provider: string;
    gateway_order_id: string;
    amount_minor: number;
    currency: string;
    status: string;
    checkout_url: string | null;
    raw_response: Record<string, unknown>;
}

/**
 * Narrow union of the TransactionStatus cases the success-page poll
 * actually transitions through. Mirrors App\Payments\Domain\Enums\TransactionStatus.
 * `null` is the missing-state shape from PaymentStatusResource::missing().
 *
 * The union deliberately includes the "terminal failure" cases
 * (`disputed`, `cancelled`, `expired`) that the success page handles
 * defensively in Success.svelte::derivePollState — even though the
 * poll is not expected to observe them, the type is widened so the
 * defensive `s === 'disputed' || ...` arms compile cleanly. If the
 * enum is later extended, the TS compiler will keep the union in
 * sync at the matching-site.
 */
export type PaymentStatusCase =
    | 'initialized'
    | 'pending'
    | 'authorized'
    | 'captured'
    | 'settling'
    | 'settled'
    | 'failed'
    | 'refunded'
    | 'partially_refunded'
    | 'disputed'
    | 'cancelled'
    | 'expired'
    | null;

/**
 * Donation success page read-shape (PaymentStatusResource::fromEntity output).
 */
export interface PaymentReceiptProps {
    number: string;
    download_path: string;
}

export interface PaymentStatusProps {
    gateway_order_id: string;
    status: PaymentStatusCase;
    amount_minor: number | null;
    currency_code: string | null;
    provider_code: string | null;
    captured_at: string | null;
    failed_at: string | null;
    last_failure_reason: string | null;
    public_key_id: string | null;
    receipt: PaymentReceiptProps | null;
}

/**
 * Receipt detail shape (ReceiptDraft + Receipt::toArray()).
 */
export interface ReceiptDraftProps {
    transaction_id: string;
    donation_id: string;
    receipt_number: string;
    file_asset_id: string;
    issued_at: string;
    content_hash: string;
    delivery_channel: string | null;
    delivery_address: string | null;
    amount_in_words: string;
}

/**
 * Canonical public-read shape for the receipt detail page. Mirrors
 * `App\Payments\Domain\DTOs\ReceiptDocument::toReadProjection()` exactly
 * (snake_case). The document is produced by the receipt substrate's
 * types worker — one typed snapshot shared with the rendered PDF and the
 * receipt email, so these fields can never drift from the document.
 *
 * Keep this in sync with ReceiptDocument::toReadProjection() — both ends
 * are hand-maintained; the codegen at `php artisan inertia:dump-types`
 * covers DTOs/ValueObjects, not this projection.
 *
 * Access gating: the page (and its PDF links) require `?t=<access_token>`
 * in the URL. The token is intentionally NOT echoed in this payload.
 */
export interface ReceiptProps {
    receipt_number: string;
    fy_label: string;
    campaign_title_snapshot: string;
    campaign_description: string;
    donor_name: string;
    donor_email: string | null;
    amount_minor: number;
    currency_code: string;
    amount_display: string;
    amount_in_words: string | null;
    payment_reference: string;
    payment_date_display: string;
    issued_date_display: string;
    is_tax_deductible: boolean;
    tax_80g_eligible: boolean;
    tax_80g_certificate_number: string | null;
    tax_80g_registration_number: string | null;
    trust_name: string;
    trust_address: string;
    trust_email: string;
    trust_phone: string;
    trust_pan: string | null;
    trust_tan: string | null;
    trust_12a_number: string | null;
    content_hash: string;
    state: string;
    generated_at: string;
}

/* ──────────────────────────────────────────────────────────────────────
 * Homepage prose aggregate
 * ──────────────────────────────────────────────────────────────────────
 *
 * Mirrors App\Cms\Domain\ValueObjects\HomepageContent. The payload
 * flows from PHP into the Svelte layer as snake_case keys (matching
 * the JSONB column convention) and the Svelte layer treats a null
 * homepageContent (HomePageProps) as a request to use
 * FALLBACK_HOMEPAGE_CONTENT from resources/js/domains/cms/homepage-fallbacks.ts.
 *
 * Programs is a fixed-length 3-tuple mirroring the backend invariant
 * (pooja, annadanam, temple_care in canonical order). Image references
 * are null when no media is attached; the Svelte layer falls back to
 * gradient panels in that case.
 */

export type HomepageProgramKey = 'pooja' | 'annadanam' | 'temple_care';

export interface HomepageStoryProps {
    eyebrow: string;
    title: string;
    body: string;
    cta_label: string | null;
    cta_url: string | null;
    image_file_id: string | null;
    /** Dedicated Pillar-Triad first-card image; falls back to `image`. */
    pillar_image_file_id: string | null;
    alt_text: string | null;
    image: PublicMediaProps | null;
    pillar_image: PublicMediaProps | null;
}

export interface HomepageMissionQuoteProps {
    eyebrow: string;
    quote: string;
    attribution: string | null;
}

export interface HomepageProgramProps {
    key: HomepageProgramKey;
    eyebrow: string;
    title: string;
    body: string;
    image_file_id: string | null;
    /** Dedicated Pillar-Triad image; falls back to `image`. */
    pillar_image_file_id: string | null;
    alt_text: string | null;
    image: PublicMediaProps | null;
    pillar_image: PublicMediaProps | null;
}

export interface HomepageTrustPanelProps {
    eyebrow: string;
    title: string;
    registration: string;
    tax_status: string;
    operating_principles: string[];
    vows: string[];
}

export interface HomepageDonateCtaProps {
    eyebrow: string;
    title: string;
    body: string;
    cta_label: string;
    cta_url: string;
}

export interface HomepageContentProps {
    version: 1;
    story: HomepageStoryProps;
    mission_quote: HomepageMissionQuoteProps;
    programs: [HomepageProgramProps, HomepageProgramProps, HomepageProgramProps];
    trust_panel: HomepageTrustPanelProps;
    donate_cta: HomepageDonateCtaProps;
}

/* ──────────────────────────────────────────────────────────────────────
 * About-page structured aggregate
 * ──────────────────────────────────────────────────────────────────────
 *
 * Mirrors App\Cms\Domain\ValueObjects\AboutPageContent. The payload
 * flows from PHP into the Svelte layer as snake_case keys (matching
 * the JSONB column convention) and the Svelte layer treats a null
 * aboutContent (AboutPageProps) as a request to use
 * FALLBACK_ABOUT_PAGE_CONTENT from resources/js/domains/cms/about-fallbacks.ts.
 *
 * The timeline is an ordered list (oldest first; the backend emits
 * ascending by year). Trustees is an ordered list (editor's chosen
 * display order). The donate_cta shape is identical to the Home page's
 * donate_cta — the same Svelte component (`DonateCtaBand`) renders
 * both. Image references are null when no media is attached; the
 * Svelte layer falls back to gradient placeholders.
 */

export interface AboutValueProps {
    eyebrow: string;
    title: string;
    body: string;
    image_file_id: string | null;
    alt_text: string | null;
    image: PublicMediaProps | null;
    pillars: AboutPillarProps[];
}

export interface AboutPillarProps {
    name: string;
    description: string;
    icon_key: string;
}

export interface AboutStoryProps {
    eyebrow: string;
    title: string;
    body: string;
    image_file_id: string | null;
    alt_text: string | null;
    image: PublicMediaProps | null;
}

export interface AboutStatProps {
    number: string;
    label: string;
    description: string | null;
}

export interface AboutProgramProps {
    eyebrow: string;
    title: string;
    body: string;
    icon_key: string;
}

export interface AboutVisitProps {
    eyebrow: string;
    title: string;
    body: string;
    address: string;
    timings: string;
    phone: string;
    dress_code: string;
    map_url: string | null;
}

export interface AboutTimelineEntryProps {
    year: number;
    title: string;
    description: string;
    image_file_id: string | null;
    image_alt_text: string | null;
    image: PublicMediaProps | null;
}

export interface AboutTrusteeProps {
    name: string;
    role: string;
    photo_file_id: string | null;
    bio: string | null;
    photo: PublicMediaProps | null;
}

export interface AboutDonateCtaProps {
    eyebrow: string;
    title: string;
    body: string;
    cta_label: string;
    cta_url: string;
}

export interface AboutPageContentProps {
    version: 2;
    values: AboutValueProps;
    story: AboutStoryProps;
    stats: AboutStatProps[];
    programs: AboutProgramProps[];
    timeline: AboutTimelineEntryProps[];
    trustees: AboutTrusteeProps[];
    visit: AboutVisitProps;
    donate_cta: AboutDonateCtaProps;
}

/* ──────────────────────────────────────────────────────────────────────
 * Legal-page structured aggregate
 * ──────────────────────────────────────────────────────────────────────
 *
 * Mirrors `App\Cms\Domain\ValueObjects\LegalPageContent`. The payload
 * flows from PHP into the Svelte layer as snake_case keys (matching
 * the JSONB column convention) and the Svelte layer treats a null
 * legalContent (LegalPageProps) as a request to use
 * FALLBACK_LEGAL_PAGE_CONTENT from resources/js/domains/cms/legal-fallbacks.ts.
 *
 * The certificates array must contain exactly four entries —
 * eighty_g (80G), twelve_a (12A), poa (Power of Attorney), tan (TAN) —
 * matching `LegalCertificate::ALLOWED_KEYS` on the PHP side. The
 * reference_number is nullable: a styled placeholder is rendered when
 * the trust office has not yet confirmed the official reference.
 *
 * The page has no hero banner editor yet — the regulatory content is
 * content-only and renders without a magazine hero. /legal therefore
 * does not carry heroBanners, html, or resolvedAt.
 */

export interface LegalIntroProps {
    eyebrow: string;
    title: string;
    body: string;
}

export type LegalCertificateKey =
    | 'eighty_g'
    | 'twelve_a'
    | 'poa'
    | 'tan';

export interface LegalCertificateProps {
    certificate_key: LegalCertificateKey;
    title: string;
    reference_number: string | null;
    description: string;
    icon_key: LegalCertificateKey;
    /**
     * Public-facing statement of the certificate's validity (e.g.
     * "A.Y. 2011-12 onwards" for the 80G approval, or "Continuing" for
     * documents with no expiry). Null when not yet determined.
     */
    validity_period: string | null;
    /**
     * Issuance date as printed on the certificate, in the issuer's
     * own wording (e.g. "23.02.2012"). Null when not yet determined.
     */
    issued_on: string | null;
    /**
     * Authority that issued the certificate (e.g. "Office of the
     * Commissioner of Income-tax, Mysore"). Null when not yet
     * determined.
     */
    issuing_authority: string | null;
}

export interface LegalPageContentProps {
    version: 1;
    intro: LegalIntroProps;
    certificates: LegalCertificateProps[];
}
