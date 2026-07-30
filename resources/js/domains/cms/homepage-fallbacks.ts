import type { HomepageContentProps } from '$shared/lib/inertia';

/**
 * Single source of fallback prose for the home page.
 *
 * `satisfies HomepageContentProps` gives a compile-time guarantee that
 * every field matches the wire contract. Svelte components import only
 * this module — they never duplicate prose in their own files.
 */
export const FALLBACK_HERO_TITLE = 'Temple Trust';

export const FALLBACK_HERO_SUBTITLE =
    'Preserving sacred traditions through daily pooja, annadanam, and the care of our temple.';

export const FALLBACK_HOMEPAGE_CONTENT: HomepageContentProps = {
    version: 1,
    story: {
        eyebrow: 'Our Story',
        title: 'A trust sustained by seva',
        body: 'Temple Trust is a registered charitable trust dedicated to the preservation of South Indian temple traditions. For over two decades, we have maintained the daily rhythms of pooja, served the community through Annadanam, and cared for the temple structure that houses our sacred practices. Our work is sustained entirely by the generosity of devotees who believe in seva as both a personal practice and a collective responsibility.',
        cta_label: 'Read more about the trust',
        cta_url: '/about',
        image_file_id: null,
        alt_text: 'Temple and sacred grounds',
        image: null,
    },
    mission_quote: {
        eyebrow: 'Our Mission',
        quote: 'To preserve the sacred traditions of daily pooja, sustain Annadanam as an offering to all who visit, and care for the temple structure — while serving as a transparent, devotee-first organisation accountable to the community we serve.',
        attribution: null,
    },
    programs: [
        {
            key: 'pooja',
            eyebrow: 'Practice',
            title: 'Daily Pooja',
            body: 'The rhythm of pooja — at sunrise, noon, and sunset — has continued unbroken for generations. Each ritual is an offering and an invitation. Your support sustains the priests, the offerings, and the sacred spaces where these practices unfold.',
            image_file_id: null,
            alt_text: 'Daily pooja',
            image: null,
        },
        {
            key: 'annadanam',
            eyebrow: 'Service',
            title: 'Annadanam',
            body: 'Free meals served daily to all who visit the temple, continuing an ancient tradition of sacred offering. Annadanam is one of the highest forms of seva, and its continuity depends entirely on the generosity of devotees.',
            image_file_id: null,
            alt_text: 'Annadanam service',
            image: null,
        },
        {
            key: 'temple_care',
            eyebrow: 'Stewardship',
            title: 'Temple Care',
            body: 'The temple structure, the sanctum, and the surrounding grounds require continuous care. From daily cleaning to structural preservation, every donation supports the physical home of these traditions.',
            image_file_id: null,
            alt_text: 'Temple care and preservation',
            image: null,
        },
    ],
    trust_panel: {
        eyebrow: 'Trust & Accountability',
        title: 'Stewardship you can rely on',
        registration:
            'The trust is formally registered and maintains its statutory records through the trust office.',
        tax_status:
            'Eligible donations receive the trust’s applicable tax documentation and official receipt.',
        operating_principles: [
            'Temple service before institutional convenience',
            'Clear records for every contribution',
            'Respectful and responsible use of offerings',
        ],
        vows: [
            'Preserve tradition',
            'Serve without discrimination',
            'Remain accountable to devotees',
        ],
    },
    donate_cta: {
        eyebrow: 'Offer Your Seva',
        title: 'Help sustain the temple’s daily work',
        body: 'Every offering supports daily worship, Annadanam, and the care of this sacred place.',
        cta_label: 'Donate Now',
        cta_url: '/donate',
    },
};
