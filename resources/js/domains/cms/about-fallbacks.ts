import type { AboutPageContentProps } from '$shared/lib/inertia';

/**
 * Single source of fallback content for the About page (V2 grammar).
 *
 * `AboutPageContentProps` gives a compile-time guarantee that every
 * field matches the wire contract. Svelte components import only this
 * module — they never duplicate copy in their own files.
 *
 * Mirrors `homepage-fallbacks.ts`. The values here are also seeded by
 * `Database\Seeders\ProductionSeeder::seedAboutPage()` so the page is
 * populated with real content the moment the row lands. Edits made in
 * either place will diverge; the seed is the canonical source, this
 * file is the placeholder of last resort.
 *
 * V2 grammar sections:
 *   - values (single)         — eyebrow/title/body + pillars[] + image
 *   - story  (single)         — magazine intro + image
 *   - stats  (3-6)            — number/label cards
 *   - programs (3-4)          — current + planned project stages
 *   - timeline (1+)           — year-anchored milestones
 *   - trustees (0+)           — leadership cards
 *   - visit  (single)         — address/timings/phone/dress
 *   - donate_cta (single)     — bottom donate CTA band copy
 *
 * Canon (see CONTENT_KNOWLEDGE_BASE.md):
 *   - VSRSMS led by Sri Ram Ram Das Guruji.
 *   - Children's-care chapter: 2007–2022, approximately 300 children.
 *   - Current project: proposed three-acre healing and service campus
 *     near Nanjangud, outside Mysore. Land under discussion.
 *   - Three-stage fundraising sequence: land → construction → cow care.
 *   - Pledge certificates and cow products are future benefits, not
 *     guaranteed.
 */

export const FALLBACK_ABOUT_PAGE_CONTENT: AboutPageContentProps = {
    version: 2,

    // ── Values ────────────────────────────────────────────────────────────
    values: {
        eyebrow: 'Our Values',
        title: 'Service, care, devotion, and purpose',
        body: 'Four commitments that hold the trust\u2019s work together — not aspirations, but the standards by which every decision is checked. They guided the children\u2019s-care chapter for nearly two decades, and they will guide the proposed campus as it is planned and built. They are the reason the work has been steady, and they are the reason the new chapter will be approached with the same seriousness as the one before it.',
        image_file_id: null,
        alt_text: null,
        image: null,
        pillars: [
            {
                name: 'Service',
                description: 'The practice of showing up, daily, without recognition. The work of the trust is sustained by people who treat service as a discipline — not a favour to anyone, and not a performance for anyone.',
                icon_key: 'care',
            },
            {
                name: 'Care',
                description: 'Attention to the actual needs of those in our charge. The children who lived with the trust, the devotees who walk in, and the cows who will live at the proposed campus — each received with the same care, not the care we imagine they need but the care they actually need.',
                icon_key: 'dharma',
            },
            {
                name: 'Devotion',
                description: 'The thread that holds the work together. The unforced steadiness of those who keep the work going, the willingness to do the next right thing without being asked, and the daily rhythm of prayer, work, and care that runs underneath the visible activity.',
                icon_key: 'devotion',
            },
            {
                name: 'Purpose',
                description: 'A clear sense of what the trust is for. Not preservation as an end in itself, but service in the world — the children first, and now a proposed home for the cows, the temple, and the people who will visit. The work is the purpose; everything else is in service to the work.',
                icon_key: 'purpose',
            },
        ],
    },

    // ── Story ─────────────────────────────────────────────────────────────
    story: {
        eyebrow: 'Our Story',
        title: 'Schools running today, the next chapter being prepared',
        body: 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam (VSRSMS) is a charitable trust led by Sri Ram Ram Das Guruji. Guruji came from a teaching background and became a respected ritual and spiritual guide in Mysore. In 2012, he received Power of Attorney for the trust and began the major service endeavour that has shaped the public work since then. Since 2007 the trust has schooled children in need of care — including blind and deaf pupils who receive free education — with schooling, life skills, and cultural training including Bharatanatyam. The schools run today: daily life is sustained by annadanam, covering food, water, and events for the children. Alongside this ongoing work, the trust\u2019s next chapter is a proposed three-acre healing and service campus near Nanjangud, outside Mysore. The campus land is under discussion; donations support the schools\u2019 daily operations and the campus fund.',
        image_file_id: null,
        alt_text: null,
        image: null,
    },

    // ── Stats ──────────────────────────────────────────────────────────────
    stats: [
        {
            number: '15+',
            label: 'Years schooling children in need of care',
            description: 'Since 2007 the trust has schooled children in need of care, including blind and deaf pupils who receive free education. The schools run today.',
        },
        {
            number: '~300',
            label: 'Children housed and educated',
            description: 'Approximately 300 children in need of care have been housed, educated, and supported across changing batches, alongside the pupils studying at the schools today.',
        },
        {
            number: 'Daily',
            label: 'Annadanam at the schools',
            description: 'Every school day the trust provides daily annadanam for the children in its care: meals, drinking water, and events at the schools. Donations sustain these daily operations.',
        },
        {
            number: '3 acres',
            label: 'Proposed campus size',
            description: 'A proposed three-acre healing and service campus near Nanjangud, outside Mysore. The land is under discussion and additional capital is required to complete the acquisition.',
        },
    ],

    // ── Programs ───────────────────────────────────────────────────────────
    programs: [
        {
            eyebrow: 'Ongoing · since 2007',
            title: 'Free schooling for blind and deaf children',
            body: 'The trust runs schools where children in need of care — including blind and deaf pupils — receive free education, with cultural training including Bharatanatyam. The schools run today and accept designated donations for their daily operations: food, water, and events for the children.',
            icon_key: 'children_education',
        },
        {
            eyebrow: 'Ongoing · daily',
            title: 'Annadanam at the schools',
            body: 'Every school day the trust provides daily annadanam for the children in its care: meals, drinking water, and events at the schools. This is a current ask — contributions sustain the daily operations of the schools, openly and accountably, alongside the campus fund.',
            icon_key: 'land_acquisition',
        },
        {
            eyebrow: 'Parallel aim · land fund',
            title: 'Acquiring land near Nanjangud',
            body: 'Alongside the schools, the trust is raising funds to acquire land near Nanjangud, outside Mysore, for the proposed three-acre campus. The land is under discussion; the acquisition requires additional capital. Until the land is secured, no construction begins. This is one of two current asks, together with the schools\u2019 daily operations.',
            icon_key: 'land_acquisition',
        },
        {
            eyebrow: 'Planned · Stage 2+',
            title: 'Build the Gaushala, temple, and healing environment',
            body: 'On the acquired land, the trust intends to build a Gaushala for the care and protection of cows, a simple Shiva temple with Kamadhenu and Shiva-family shrines, and a disciplined environment for people recovering from addiction or serious personal difficulty — then the long-term care and welfare of the resident cows, with future cow-related farming and products for devotees in accordance with the project\u2019s operating and legal framework. Construction begins after land acquisition closes. This stage is not yet fundraising.',
            icon_key: 'gaushala',
        },
    ],

    // ── Timeline ───────────────────────────────────────────────────────────
    timeline: [
        {
            year: 2007,
            title: 'Children\u2019s-care chapter begins',
            description: 'The trust begins its residential care and education programme for children in need of care, near Mysore. The first cohort of children joins the homes and the schools.',
            image_file_id: null,
            image_alt_text: null,
            image: null,
        },
        {
            year: 2012,
            title: 'Guruji receives Power of Attorney',
            description: 'Sri Ram Ram Das Guruji receives Power of Attorney for the trust and takes responsibility for the major service endeavour that has shaped the public work since then.',
            image_file_id: null,
            image_alt_text: null,
            image: null,
        },
        {
            year: 2022,
            title: 'School cohorts graduate, schools continue',
            description: 'Senior cohorts of the schools complete their education and move forward in life. The schools continue to run: new pupils — including blind and deaf children on free education — study, eat, and gather daily under annadanam.',
            image_file_id: null,
            image_alt_text: null,
            image: null,
        },
        {
            year: 2026,
            title: 'Schools plus campus: two current causes',
            description: 'The trust publicly shares its plan for a proposed three-acre healing and service campus near Nanjangud, outside Mysore, while the schools keep running. Donations sustain daily annadanam at the schools and the land fund for the campus.',
            image_file_id: null,
            image_alt_text: null,
            image: null,
        },
    ],

    // ── Trustees ───────────────────────────────────────────────────────────
    trustees: [
        {
            name: 'Sri Ram Ram Das Guruji',
            role: 'Spiritual Leader, VSRSMS',
            photo_file_id: null,
            bio: 'Sri Ram Ram Das Guruji came from a teaching background and became a respected ritual and spiritual guide in Mysore. In 2012, he received Power of Attorney for the trust and has been the principal author of the trust\u2019s service work since then. He is the visible primary actor across every public event the trust runs and the subject of the trust\u2019s published imagery. He remains the point of contact for donations, event coordination, and the trust\u2019s spiritual direction.',
            photo: null,
        },
        {
            name: 'Board of Trustees',
            role: 'To be published',
            photo_file_id: null,
            bio: 'The composition of the wider board is recorded with the trust office. A full directory — names, roles, and short bios — will be published once the board\u2019s formal roster is registered and approved.',
            photo: null,
        },
    ],

    // ── Visit ──────────────────────────────────────────────────────────────
    visit: {
        eyebrow: 'Plan your visit',
        title: 'The trust operates near Nanjangud, outside Mysore',
        body: 'Office hours, specific pooja timings, and gate details will be published by the trust office once they are finalised for the current calendar. Visitors who wish to attend a trust event or to ask about a donation are asked to write to the office in advance.',
        address: 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam\nNanjangud, Karnataka (outside Mysore)',
        timings: 'Office hours published by the trust office',
        phone: '+91 98441 32318',
        dress_code: 'Modest clothing preferred for visits to the temple premises. Specific dress requirements for the inner sanctum will be published with the formal timings.',
        map_url: null,
    },

    // ── Donate CTA ─────────────────────────────────────────────────────────
    donate_cta: {
        eyebrow: 'Support the schools and the campus',
        title: 'Sustain daily annadanam, help build the campus',
        body: 'Donations sustain the daily operations of the schools — food, water, and events for the children — and support the land fund for the proposed healing and service campus. Construction follows once the land is secured. Cow care and welfare follow once the campus is operational. Contributions of any size are received with the trust\u2019s gratitude and acknowledged with an official receipt.',
        cta_label: 'Support the Schools and Campus Fund',
        cta_url: '/donate',
    },
};
