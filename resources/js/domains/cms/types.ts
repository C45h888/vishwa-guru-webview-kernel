import type { AppPageProps, PublicMediaProps } from '$shared/lib/inertia';

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

export interface HomePageProps extends AppPageProps {
    page: StaticPageSummary;
    heroBanners: HeroBannerProps[];
    resolvedReferences: ResolvedReferenceProps[];
    html: string;
    resolvedAt: string;
    featuredCampaigns: FeaturedItemSummary[];
    featuredEvents: FeaturedItemSummary[];
    featuredGalleries: FeaturedItemSummary[];
}

export interface HomeEmptyProps extends AppPageProps {
    featuredCampaigns: FeaturedItemSummary[];
    featuredEvents: FeaturedItemSummary[];
    featuredGalleries: FeaturedItemSummary[];
}
