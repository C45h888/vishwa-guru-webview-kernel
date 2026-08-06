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
 * populated with real content the moment the row lands. Edits made
 * in either place will diverge; the seed is the canonical source, this
 * file is the placeholder of last resort.
 *
 * V2 grammar sections:
 *   - values (single)         — eyebrow/title/body + pillars[] + image
 *   - story  (single)         — magazine intro + image
 *   - stats  (3-6)            — number/label cards
 *   - programs (3-4)          — ongoing programmes with icon
 *   - timeline (1+)           — year-anchored milestones
 *   - trustees (0+)           — leadership cards
 *   - visit  (single)         — address/timings/phone/dress
 *   - donate_cta (single)     — bottom donate CTA band copy
 */
export const FALLBACK_ABOUT_PAGE_CONTENT: AboutPageContentProps = {
    version: 2,

    // ── Values ────────────────────────────────────────────────────────────
    values: {
        eyebrow: 'Our Values',
        title: 'Dharma, Care, Devotion, Purpose',
        body: 'Four commitments that the trust holds as operating doctrine — not aspirations, but the standards by which every decision is checked. They are older than any single project of the trust, and they will outlast whichever projects come and go. They are the reason the children have stayed housed and educated through the years, and they are the reason the new chapter — the Gaushala and the Shiva temple — has been taken up with the same seriousness as the work that came before.',
        image_file_id: null,
        alt_text: null,
        image: null,
        pillars: [
            {
                name: 'Dharma',
                description: 'The discipline of right action. Every decision at the trust is checked against dharma before it is checked against convenience, cost, or speed — what is the right thing to do here, and who is it right for.',
                icon_key: 'dharma',
            },
            {
                name: 'Care',
                description: 'Compassion held as practice, not as sentiment. The children who have lived with us, the cows who will live at the Gaushala, the devotees who walk in — all received with the same attention to their actual needs, not the ones we imagine they have.',
                icon_key: 'care',
            },
            {
                name: 'Devotion',
                description: 'The thread that holds the work together. The daily rhythm of service, the unforced steadiness of those who keep the work going, the willingness to do the next right thing without being asked and without recognition.',
                icon_key: 'devotion',
            },
            {
                name: 'Purpose',
                description: 'A clear sense of what the trust is for. Not preservation as an end in itself, but seva in the world — shelter, education, and now a sacred home for the cows. The work is the purpose; everything else is in service to the work.',
                icon_key: 'purpose',
            },
        ],
    },

    // ── Story ─────────────────────────────────────────────────────────────
    story: {
        eyebrow: 'Our Story',
        title: 'Eighteen years of children, and now a home for the cows',
        body: "The trust was founded in 2008 and has been operating from Mysore ever since. In its first chapter the work was the children: housing them, schooling them, raising them into steady adult lives. Many of the children who came through the trust's early programmes are now contributing to its work themselves, in capacities that range from the kitchen to the office to the management of new projects. The trust runs multiple establishments across Mysore and has accumulated a great deal of operational practice over the years — none of it abstract, all of it grounded in the daily work. The trust now acts from a branch that holds the main trust's power of attorney, with the branch trustee as the visible primary actor across all of the trust's events, donations, and imagery. The branch's current capital project is the acquisition of land on which to build a Gaushala — a shelter for the sacred cows — and a Shiva temple. The land must be bought before the construction can begin. Every donation received through this site goes to that work.",
        image_file_id: null,
        alt_text: null,
        image: null,
    },

    // ── Stats ──────────────────────────────────────────────────────────────
    stats: [
        {
            number: '18+',
            label: 'Years of continuous service',
            description: 'Since the trust was founded in 2008. The work has not stopped, and the rhythm of the day has not been broken, in any of those years.',
        },
        {
            number: '—',
            label: 'Children supported through our programmes',
            description: 'Many years of residential care, schooling, and steady adult mentoring for the children in our charge. Exact figures are kept by the trust office and shared on request.',
        },
        {
            number: 'Multiple',
            label: 'Establishments operating across Mysore',
            description: 'Residential homes, classrooms, and operational premises — each one a working part of the trust\'s daily rhythm, none of them ornamental.',
        },
        {
            number: '1',
            label: 'Active capital project',
            description: 'Land acquisition for the Gaushala and the Shiva temple. The single current ask the trust is raising funds for, until it is met.',
        },
    ],

    // ── Programs ───────────────────────────────────────────────────────────
    programs: [
        {
            eyebrow: 'Programme · 01',
            title: "Children's Education",
            body: 'The trust has run a continuous schooling programme since its early years — not as an enrichment to the residential work, but as one of its two pillars. Children in the trust\'s care learn at age-appropriate level and at a pace that respects what each child has actually come in with. The teaching is in person, the curriculum is broad, and the expectation is that the children leave the programme able to continue their own education.',
            icon_key: 'children_education',
        },
        {
            eyebrow: 'Programme · 02',
            title: "Children's Housing",
            body: 'Residential care for the children in the trust\'s charge, in Mysore, with the rhythms of a real home rather than the routines of an institution. The children are raised, not processed. The first generation of children who came through this programme are now contributing to the trust\'s work in their own right.',
            icon_key: 'children_housing',
        },
        {
            eyebrow: 'Programme · 03',
            title: 'Gaushala',
            body: 'The trust\'s current capital project. A shelter for the sacred cows, to be built on land that the trust is in the process of acquiring. The Gaushala is conceived as a long-term home for the cows, not a short-term campaign — care that continues across years, with the same seriousness as the children\'s programmes.',
            icon_key: 'gaushala',
        },
        {
            eyebrow: 'Programme · 04',
            title: 'Shiva Temple',
            body: 'A temple dedicated to Shiva, to be built alongside the Gaushala on the same land. The temple is conceived as the spiritual centre of the trust\'s Mysore campus, and as the place from which the daily rhythms of the new chapter will be conducted. Construction begins once the land is secured.',
            icon_key: 'shiva_temple',
        },
    ],

    // ── Timeline ───────────────────────────────────────────────────────────
    timeline: [
        {
            year: 2008,
            title: 'Trust founded',
            description: 'The trust is established as a charitable body, with the children\'s home as the anchor of every other activity to follow.',
            image_file_id: null,
            image_alt_text: null,
            image: null,
        },
        {
            year: 2014,
            title: "Children's programmes expand",
            description: 'Residential care, schooling, and vocational mentoring grow into their present scale — each one a working part of the trust\'s daily rhythm in Mysore.',
            image_file_id: null,
            image_alt_text: null,
            image: null,
        },
        {
            year: 2022,
            title: 'Branch consolidation under POA',
            description: 'The trust\'s Mysore operations consolidate under a branch that holds the main trust\'s power of attorney. The branch trustee becomes the visible primary actor across events, donations, and imagery.',
            image_file_id: null,
            image_alt_text: null,
            image: null,
        },
        {
            year: 2026,
            title: 'Gaushala + Shiva temple campaign',
            description: 'The trust begins raising funds to acquire land on which to build a Gaushala (cow shelter) and a Shiva temple. This is the current capital project.',
            image_file_id: null,
            image_alt_text: null,
            image: null,
        },
    ],

    // ── Trustees ───────────────────────────────────────────────────────────
    trustees: [
        {
            name: 'Sri-ram Ramdas',
            role: 'Trustee & Spiritual Head, Power of Attorney',
            photo_file_id: null,
            bio: 'Sri-ram Ramdas holds the power of attorney for the main trust and acts as the trustee of its Mysore branch. He is the visible primary actor across every public event the trust runs and the subject of the trust\'s published imagery. He has been the principal author of the trust\'s pivot to the Gaushala and Shiva temple project, and remains the point of contact for donations, event coordination, and the trust\'s spiritual direction.',
            photo: null,
        },
        {
            name: 'Trustee',
            role: 'Board of Trustees',
            photo_file_id: null,
            bio: 'The composition of the wider board is recorded with the trust office. Trustee cards will populate this section as additional board members are formally listed.',
            photo: null,
        },
        {
            name: 'Treasurer',
            role: 'Board of Trustees',
            photo_file_id: null,
            bio: 'The treasurer of the trust is recorded with the trust office. A full bio will appear here when the trust publishes the formal board roster.',
            photo: null,
        },
    ],

    // ── Visit ──────────────────────────────────────────────────────────────
    visit: {
        eyebrow: 'Plan your visit',
        title: 'The trust operates from Mysore',
        body: 'The trust\'s Mysore premises are operational year-round. Specific pooja timings, gate hours, and darshan windows are published by the temple office once they are finalised for the current calendar. Visitors who wish to attend any of the trust\'s events or to see the children\'s programmes in operation are asked to write to the temple office in advance.',
        address: 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam\nMysore, Karnataka',
        timings: 'Temple hours and event timings will be published by the temple office',
        phone: '+91 98441 32318',
        dress_code: 'Modest clothing preferred for visits to the temple premises. Specific dress requirements for the inner sanctum will be published with the formal timings.',
        map_url: null,
    },

    // ── Donate CTA ─────────────────────────────────────────────────────────
    donate_cta: {
        eyebrow: 'Offer Your Seva',
        title: 'Help build the Gaushala and the Shiva temple',
        body: 'Every donation received through this site goes to the current capital project — acquiring the land on which the Gaushala and the Shiva temple will stand. Contributions of any size are received with the trust\'s gratitude and acknowledged with an official receipt.',
        cta_label: 'Donate Now',
        cta_url: '/donate',
    },
};