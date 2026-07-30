export interface AuthUser {
    id: string;
    name: string;
    email: string;
}

export interface SharedPageProps {
    appName: string;
    appUrl: string;
    authUser: AuthUser | null;
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
 * Donation success page read-shape (PaymentStatusResource::fromEntity output).
 */
export interface PaymentStatusProps {
    gateway_order_id: string;
    status: string | null;
    amount_minor: number | null;
    currency_code: string | null;
    provider_code: string | null;
    captured_at: string | null;
    failed_at: string | null;
    last_failure_reason: string | null;
    public_key_id: string;
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
    alt_text: string | null;
    image: PublicMediaProps | null;
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
    alt_text: string | null;
    image: PublicMediaProps | null;
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
}

export interface AboutTimelineEntryProps {
    year: number;
    title: string;
    description: string;
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
    version: 1;
    values: AboutValueProps;
    timeline: AboutTimelineEntryProps[];
    trustees: AboutTrusteeProps[];
    donate_cta: AboutDonateCtaProps;
}
