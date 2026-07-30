import type { AboutPageContentProps } from '$shared/lib/inertia';

/**
 * Single source of fallback content for the About page.
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
 */
export const FALLBACK_ABOUT_PAGE_CONTENT: AboutPageContentProps = {
    version: 1,
    values: {
        eyebrow: 'Our Values',
        title: 'Seva, Satya, and Smriti',
        body: 'The trust is sustained by three commitments: seva — the discipline of self-giving service; satya — clear, honest stewardship of every offering; and smriti — the careful preservation of the rhythms, language, and practices that make a temple a living tradition. We hold ourselves to these not as aspirations but as the operating doctrine of every decision the trust makes.',
        image_file_id: null,
        alt_text: null,
        image: null,
    },
    timeline: [
        {
            year: 1998,
            title: 'Temple founded',
            description:
                'A small group of devotees established the temple on land donated by the founding family, with daily pooja as the anchor of every other activity to follow.',
        },
        {
            year: 2005,
            title: 'Annadanam hall opens',
            description:
                'A dedicated hall was added to serve the community meal every day of the year, regardless of festival or quiet season. Annadanam has been offered without interruption since.',
        },
        {
            year: 2012,
            title: 'Priest training program',
            description:
                'The trust began an in-house training program for temple priests, ensuring that the next generation of ritualists learn the full agamic discipline rather than a simplified subset.',
        },
        {
            year: 2019,
            title: 'Temple structure restoration',
            description:
                'A multi-year restoration of the vimana and outer prakara was completed using traditional materials and craftspeople, with funding drawn entirely from devotee offerings.',
        },
        {
            year: 2024,
            title: 'Online donations and receipts',
            description:
                'The trust launched its public digital platform so devotees anywhere in the world can offer seva and receive an official receipt and the temple\'s gratitude.',
        },
    ],
    trustees: [
        {
            name: 'Dr. Anjali Rao',
            role: 'Chair, Board of Trustees',
            photo_file_id: null,
            bio: 'A Sanskrit scholar and practising devotee, Anjali has served on the board since 2014 and has chaired it since 2020. She guides the trust\'s academic and ritual standards.',
            photo: null,
        },
        {
            name: 'Sundaram Iyer',
            role: 'Treasurer',
            photo_file_id: null,
            bio: 'A retired banker, Sundaram has overseen the trust\'s finances for over a decade and is the principal author of the annual audit and donor receipts process.',
            photo: null,
        },
        {
            name: 'Lakshmi Narayanan',
            role: 'Trustee, Annadanam',
            photo_file_id: null,
            bio: 'Lakshmi leads the daily Annadanam programme and coordinates the volunteers who prepare and serve the community meal each day of the year.',
            photo: null,
        },
    ],
    donate_cta: {
        eyebrow: 'Offer Your Seva',
        title: "Help sustain the temple's daily work",
        body: 'Every offering supports daily pooja, Annadanam, and the care of this sacred place. Contributions of any size are received with gratitude and acknowledged with a receipt.',
        cta_label: 'Donate Now',
        cta_url: '/donate',
    },
};
