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

export const FALLBACK_HERO_TITLE = 'VSRSMS — A charitable trust, now building its next chapter';

export const FALLBACK_HERO_SUBTITLE =
    'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam. Approximately 300 children in need of care were housed and educated between 2007 and 2022. Now raising funds to acquire land near Nanjangud, outside Mysore, for a proposed healing and service campus.';

export const FALLBACK_HOMEPAGE_CONTENT: HomepageContentProps = {
    version: 1,
    story: {
        eyebrow: 'Our Story',
        title: 'Two chapters, one discipline of service',
        body: 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS) is a charitable trust led by Sri Ram Ram Das Guruji. Between 2007 and 2022, the trust ran a residential care and education programme for approximately 300 children in need of care. The children received schooling, life skills, and cultural training including Bharatanatyam. That chapter is now complete — the children have completed their education and moved forward in life. The trust is now raising funds for its next chapter: a proposed three-acre healing and service campus near Nanjangud, outside Mysore, anchored by a Gaushala, a simple Shiva temple, and a disciplined environment for recovery and service. The land is under discussion; we are not yet building. We are asking for your support to acquire it.',
        cta_label: 'Learn about the trust',
        cta_url: '/about',
        image_file_id: null,
        alt_text: 'A glimpse of the trust work',
        image: null,
    },
    mission_quote: {
        eyebrow: 'Our Mission',
        quote: 'To complete the children\u2019s care chapter with the same seriousness it has always been held, and to acquire the land on which the next chapter can begin — steady, accountable, and open to every devotee who chooses to be part of it.',
        attribution: 'Sri Ram Ram Das Guruji',
    },
    programs: [
        {
            key: 'pooja',
            eyebrow: 'First chapter · 2007–2022',
            title: 'Children\u2019s care and education',
            body: 'A residential care and education programme for children in need of care. Approximately 300 children were housed, educated, and supported across changing batches. The chapter concluded because the children completed their education and moved forward in life. Many of the first generation contribute to the work of the trust in their own right.',
            image_file_id: null,
            alt_text: 'Children from the trust\u2019s first chapter',
            image: null,
        },
        {
            key: 'annadanam',
            eyebrow: 'Current',
            title: 'Land acquisition',
            body: 'The trust is raising funds to acquire land near Nanjangud, outside Mysore, for the proposed healing and service campus. The land is under discussion; the acquisition requires additional capital. Until the land is secured, no construction begins. This is the current ask, and the only construction stage where contributions are needed today.',
            image_file_id: null,
            alt_text: 'Proposed land near Nanjangud',
            image: null,
        },
        {
            key: 'temple_care',
            eyebrow: 'Planned',
            title: 'Gaushala, temple, and healing environment',
            body: 'On the secured land, the trust intends to build a Gaushala for the care and protection of cows, a simple Shiva temple with Kamadhenu and Shiva-family shrines, and a disciplined environment for people recovering from addiction or serious personal difficulty. Future plans also include cow-related farming and products for devotees, and community events and fundraisers. Construction begins after the land is acquired; cow care and welfare follow once the campus becomes operational.',
            image_file_id: null,
            alt_text: 'The proposed campus skyline',
            image: null,
        },
    ],
    trust_panel: {
        eyebrow: 'Trust & Accountability',
        title: 'A trust that earns its support',
        registration:
            'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam is a charitable trust registered in India. The current campaign is the land acquisition for the proposed campus near Nanjangud, Karnataka.',
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
        eyebrow: 'Support the land acquisition',
        title: 'Help us acquire the land for the new campus',
        body: 'Every donation supports the current campaign — acquiring the land on which the proposed healing and service campus will be built. Construction follows once the land is secured. Contributions of any size are received with the trust\u2019s gratitude and acknowledged with an official receipt.',
        cta_label: 'Support the Land Acquisition Campaign',
        cta_url: '/donate',
    },
};
