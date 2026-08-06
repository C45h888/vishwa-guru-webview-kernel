import type { HomepageContentProps } from '$shared/lib/inertia';

/**
 * Single source of fallback prose for the home page.
 *
 * `satisfies HomepageContentProps` gives a compile-time guarantee that
 * every field matches the wire contract. Svelte components import only
 * this module — they never duplicate prose in their own files.
 */
export const FALLBACK_HERO_TITLE = 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam';

export const FALLBACK_HERO_SUBTITLE =
    "Eighteen years of children's education and housing. Now building a Gaushala and a Shiva temple — and asking for your seva to help acquire the land.";

export const FALLBACK_HOMEPAGE_CONTENT: HomepageContentProps = {
    version: 1,
    story: {
        eyebrow: 'Our Story',
        title: 'A trust in its second chapter',
        body: 'Sri Vishwaguru Sri Sri Sriram Shishyavrundham Mahasamsthanam is a registered charitable trust founded in 2008 and operating from Mysore. The first chapter of the trust\'s work was the children — housing them, schooling them, raising them into steady adult lives across multiple establishments in Mysore. The trust\'s Mysore branch holds the main trust\'s power of attorney and is now raising funds for its second chapter: the acquisition of land on which to build a Gaushala, a shelter for the sacred cows, and a temple dedicated to Shiva. The trust\'s work has always been sustained by devotees who treat seva as both a personal practice and a collective responsibility. That is still how the new chapter will be funded.',
        cta_label: 'Read more about the trust',
        cta_url: '/about',
        image_file_id: null,
        alt_text: 'Temple and sacred grounds',
        image: null,
    },
    mission_quote: {
        eyebrow: 'Our Mission',
        quote: 'To shelter and educate children in our care, to live dharmically in the world, and now to secure a sacred home for the cows — every act of seva held to the same standard of care, devotion, and purpose.',
        attribution: null,
    },
    programs: [
        {
            key: 'pooja',
            eyebrow: 'Education',
            title: "Children's Education",
            body: 'Continuous schooling for the children in the trust\'s care — in person, broad in curriculum, paced to what each child has actually come in with. The first generation of children who came through this programme are now contributing to the trust\'s own work.',
            image_file_id: null,
            alt_text: "Children's education programme",
            image: null,
        },
        {
            key: 'annadanam',
            eyebrow: 'Care',
            title: "Children's Housing",
            body: 'Residential care in Mysore, with the rhythms of a real home rather than the routines of an institution. The children are raised, not processed. Many of them grow up to give back to the trust in their own right.',
            image_file_id: null,
            alt_text: "Children's residential care",
            image: null,
        },
        {
            key: 'temple_care',
            eyebrow: 'Stewardship',
            title: 'Gaushala Project',
            body: 'The trust\'s current capital project. Land is being acquired on which to build a Gaushala — a shelter for the sacred cows — and a Shiva temple. Construction begins when the land is secured. Every donation through this site goes to this work.',
            image_file_id: null,
            alt_text: 'Gaushala land project',
            image: null,
        },
    ],
    trust_panel: {
        eyebrow: 'Trust & Accountability',
        title: 'Stewardship you can rely on',
        registration:
            'The trust is formally registered and operates from Mysore under a power of attorney held by its trustee.',
        tax_status:
            'Donation receipts are issued for every contribution. Tax-deductibility status will be confirmed at the time of each donation.',
        operating_principles: [
            'Dharma before convenience',
            'Care held as practice, not sentiment',
            'Devotion without recognition',
            'Purpose in service of the work',
        ],
        vows: [
            'Hold the children with the same seriousness we have always held them',
            'Treat every donation with the same care we treat our own resources',
            'Remain accountable to devotees and to the public record',
        ],
    },
    donate_cta: {
        eyebrow: 'Offer Your Seva',
        title: 'Help build the Gaushala and the Shiva temple',
        body: 'Every offering supports the current capital project — acquiring the land on which the Gaushala and the Shiva temple will stand.',
        cta_label: 'Donate Now',
        cta_url: '/donate',
    },
};