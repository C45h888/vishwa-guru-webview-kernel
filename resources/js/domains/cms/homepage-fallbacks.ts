import type { HomepageContentProps } from '$shared/lib/inertia';

/**
 * Single source of fallback prose for the home page.
 *
 * `satisfies HomepageContentProps` gives a compile-time guarantee that
 * every field matches the wire contract. Svelte components import only
 * this module — they never duplicate prose in their own files.
 *
 * Canon: VSRSMS — Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam.
 * The full legal name is held in `appName` (env-driven) and surfaces in
 * the footer, metadata, and receipt titles. The home-page hero uses the
 * short brand VSRSMS so the headline is legible; the legal name appears
 * in the eyebrow line directly below.
 *
 * Narrative canon (see CONTENT_KNOWLEDGE_BASE.md):
 *   - Spiritual leader: Sri Ram Ram Das Guruji.
 *   - First chapter: 2007–2022, residential care & education for
 *     approximately 300 children in need of care. Concluded.
 *   - Second chapter: a proposed three-acre healing and service campus
 *     near Nanjangud, outside Mysore. Land is under discussion; the
 *     Gaushala, a simple Shiva temple, and a healing environment are
 *     planned but not yet built. The first fundraising stage is land
 *     acquisition.
 */

export const FALLBACK_HERO_TITLE = 'VSRSMS — Schooling children in need, building the next chapter';

export const FALLBACK_HERO_SUBTITLE =
    'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam. Since 2007 the trust has schooled children in need of care — including blind and deaf pupils receiving free education — sustained by daily annadanam. One pooled Schools & Campus Fund supports daily school operations and the staged proposed campus programme near Nanjangud, outside Mysore.';

export const FALLBACK_HOMEPAGE_CONTENT: HomepageContentProps = {
    version: 1,
    story: {
        eyebrow: 'Our Story',
        title: 'Ongoing schooling, daily annadanam, and the next chapter',
        body: 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS) is a charitable trust led by Sri Ram Ram Das Guruji. Since 2007 the trust has schooled children in need of care — including blind and deaf pupils who receive free education. Daily life at the schools is sustained by annadanam: food, water, and events for the children. Alongside this ongoing work, the trust is raising funds for its next chapter: a proposed three-acre healing and service campus near Nanjangud, outside Mysore, anchored by a Gaushala, a simple Shiva temple, and a disciplined environment for recovery and service. The campus land is under discussion; the schools run today. Donations are pooled through one Schools and Campus Fund across daily school operations and the staged campus programme, including land acquisition and planned Gaushala, temple, healing-environment, and cow-care work.',
        cta_label: 'Learn about the trust',
        cta_url: '/about',
        image_file_id: null,
        pillar_image_file_id: null,
        alt_text: 'A glimpse of the trust work',
        image: null,
        pillar_image: null,
    },
    mission_quote: {
        eyebrow: 'Our Mission',
        quote: 'To school every child in our care with the same seriousness it has always been held — daily food, water, learning, and care — and to build the land on which the next chapter can stand: steady, accountable, and open to every devotee who chooses to be part of it.',
        attribution: 'Sri Ram Ram Das Guruji',
    },
    programs: [
        {
            key: 'pooja',
            eyebrow: 'Ongoing · since 2007',
            title: 'Free schooling for blind and deaf children',
            body: 'The trust runs schools where children in need of care — including blind and deaf pupils — receive free education. Schooling is in person, broad in curriculum, and paced to what each child has actually come in with. Daily operations — food, water, and events for the children — are sustained by annadanam and supported through the pooled fund.',
            image_file_id: null,
            alt_text: 'Children at the trust\u2019s schools',
            image: null,
        },
        {
            key: 'annadanam',
            eyebrow: 'Ongoing · daily',
            title: 'Annadanam: food, water, and school events',
            body: 'Every school day the trust provides daily annadanam for the children in its care: meals, drinking water, and events at the schools. The pooled fund supports these daily operations alongside the staged campus programme.',
            image_file_id: null,
            alt_text: 'Daily annadanam at the schools',
            image: null,
        },
        {
            key: 'temple_care',
            eyebrow: 'Parallel aim',
            title: 'Land and campus: Gaushala, temple, healing environment',
            body: 'Alongside daily school operations, the pooled fund supports the proposed campus near Nanjangud: land acquisition, followed by planned Gaushala, temple, healing-environment, and cow-care work as stages proceed. The land is under discussion; construction follows once it is secured. Gifts are not restricted to one sub-project.',
            image_file_id: null,
            alt_text: 'The proposed campus near Nanjangud',
            image: null,
        },
    ],
    trust_panel: {
        eyebrow: 'Trust & Accountability',
        title: 'A trust that earns its support',
        registration:
            'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam is a charitable trust registered in India. Donations are pooled across the daily operations of the schools and the staged campus programme near Nanjangud, including land acquisition and planned Gaushala, temple, healing-environment, and cow-care work.',
        tax_status:
            'An official receipt is issued for every donation. Tax-deductibility, including 80G certificates where applicable, is confirmed at the time of each donation in line with applicable law. The trust will publish a dedicated legal page once it has been reviewed and approved.',
        operating_principles: [
            'Service held as practice, not sentiment',
            'Children in our care are raised, not processed',
            'Devotion without recognition',
            'Disciplined stewardship of every offering',
        ],
        vows: [
            'Honour the children\u2019s-care chapter with the same seriousness it has always been held',
            'Treat every donation with the same care we treat our own resources',
            'Publish how campaign funds are spent when each stage closes',
            'Describe the project honestly — proposed, planned, under discussion, or built',
        ],
    },
    donate_cta: {
        eyebrow: 'Support the schools and the campus',
        title: 'Sustain daily annadanam, help build the campus',
        body: 'Donations are pooled through one Schools and Campus Fund across daily school operations and the staged campus programme, including land acquisition, planned Gaushala and temple work, the healing environment, and cow care. A gift is not restricted to one sub-project; every contribution is acknowledged with an official receipt.',
        cta_label: 'Support the pooled fund',
        cta_url: '/donate',
    },
};
