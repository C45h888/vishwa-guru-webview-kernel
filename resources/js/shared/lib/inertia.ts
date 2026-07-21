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
