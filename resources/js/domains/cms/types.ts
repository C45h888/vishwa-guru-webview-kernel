import type {
    AppPageProps,
    PublicMediaProps,
    CampaignSummaryProps,
    EventSummaryProps,
    GallerySummaryProps,
    HomepageContentProps,
    HomepageStoryProps,
    HomepageMissionQuoteProps,
    HomepageProgramProps,
    HomepageTrustPanelProps,
    HomepageDonateCtaProps,
    AboutPageContentProps,
    AboutValueProps,
    AboutPillarProps,
    AboutStoryProps,
    AboutStatProps,
    AboutProgramProps,
    AboutVisitProps,
    AboutTimelineEntryProps,
    AboutTrusteeProps,
    AboutDonateCtaProps,
    LegalPageContentProps,
    LegalIntroProps,
    LegalCertificateProps,
} from '$shared/lib/inertia';

export type {
    HomepageContentProps,
    HomepageStoryProps,
    HomepageMissionQuoteProps,
    HomepageProgramProps,
    HomepageTrustPanelProps,
    HomepageDonateCtaProps,
    AboutPageContentProps,
    AboutValueProps,
    AboutPillarProps,
    AboutStoryProps,
    AboutStatProps,
    AboutProgramProps,
    AboutVisitProps,
    AboutTimelineEntryProps,
    AboutTrusteeProps,
    AboutDonateCtaProps,
    LegalPageContentProps,
    LegalIntroProps,
    LegalCertificateProps,
};

export interface HeroBannerProps {
    id: string;
    title: string | null;
    subtitle: string | null;
    cta_label: string | null;
    cta_url: string | null;
    image_file_id: string | null;
    mobile_image_file_id: string | null;
    image?: PublicMediaProps | null;
    mobile_image?: PublicMediaProps | null;
    state: string;
    display_order: number;
    starts_at: string | null;
    ends_at: string | null;
}

export interface ResolvedReferenceProps {
    reference_type: string;
    reference_id: string;
    context: string | null;
    display_order: number;
    status: string;
    payload: unknown | null;
}

export interface StaticPageSummary {
    id: string;
    slug: string;
    title: string;
    meta_description: string | null;
    state: string;
    is_homepage: boolean;
}

export interface FeaturedItemSummary {
    id: string;
    slug: string;
    title: string;
    short_description: string | null;
}

export type HomeFeaturedCampaign = CampaignSummaryProps;
export type HomeFeaturedEvent = EventSummaryProps;
export type HomeFeaturedGallery = GallerySummaryProps;

export interface HomePageProps extends AppPageProps {
    page: StaticPageSummary;
    homepageContent: HomepageContentProps | null;
    heroBanners: HeroBannerProps[];
    resolvedReferences: ResolvedReferenceProps[];
    html: string;
    resolvedAt: string;
    featuredCampaigns: HomeFeaturedCampaign[];
    featuredEvents: HomeFeaturedEvent[];
    featuredGalleries: HomeFeaturedGallery[];
}

export interface CmsPageProps extends AppPageProps {
    page: StaticPageSummary;
    heroBanners: HeroBannerProps[];
    html: string;
    resolvedAt: string;
}

export interface ContactPointProps {
    id: string;
    label: string;
    contact_type: string;
    value: string;
    is_primary: boolean;
    display_order: number;
}

export interface ContactPageProps extends AppPageProps {
    contactPoints: ContactPointProps[];
    /** OpenStreetMap raster tiles around the primary office address. */
    mapTiles: Array<{ url: string; row: number; column: number }>;
    /** Tile pixel offsets that keep the office pin centered in the map frame. */
    mapTileOffsetX: number;
    mapTileOffsetY: number;
    /** Plain-text address used to label the map, for the aria-title. */
    mapAddress: string | null;
    /** Human-facing Google Maps directions link (the consumer site, not the iframe). */
    mapOpenUrl: string | null;
}

export interface AboutPageProps extends AppPageProps {
    page: StaticPageSummary;
    aboutContent: AboutPageContentProps | null;
    heroBanners: HeroBannerProps[];
    featuredGalleries: HomeFeaturedGallery[];
    html: string;
    resolvedAt: string;
}

export interface LegalPageProps extends AppPageProps {
    page: StaticPageSummary;
    legalContent: LegalPageContentProps | null;
}
