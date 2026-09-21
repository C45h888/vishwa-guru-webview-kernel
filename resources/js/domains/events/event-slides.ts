/**
 * EventHeroSlideshow — slide config.
 *
 * The events page is the "asset class" that showcases what the temple does
 * over the year. Three slides cover the breadth: a daily rhythm, a major
 * festival, and an annual celebration. Kept short to avoid visual repetition
 * between near-identical ceremony shots.
 *
 * No backend coupling. When the events table is populated, this config
 * can be replaced by a server-driven slide list without changing the
 * component contract (the prop is `{ src, eyebrow, title, body, alt }[]`).
 */
export interface EventSlide {
    src: string;
    alt: string;
    eyebrow: string;
    title: string;
    body: string;
}

export const EVENT_SLIDES: EventSlide[] = [
    {
        src: '/storage/cms-media/events-slide-2-varalakshmi.webp',
        alt: 'Daily life at the trust\u2019s schools',
        eyebrow: 'Ongoing \u00b7 schools',
        title: 'The schools run today',
        body: 'Children in need of care — including blind and deaf pupils on free education — study, eat, and gather daily. Annadanam covers food, water, and school events; donations sustain these daily operations.',
    },
    {
        src: '/storage/cms-media/events-slide-7-cultural-evenings.webp',
        alt: 'The proposed campus near Nanjangud',
        eyebrow: 'Current \u00b7 Stage 1',
        title: 'Acquiring the land',
        body: 'The trust is raising funds to acquire land near Nanjangud, outside Mysore, for the proposed three-acre healing and service campus.',
    },
    {
        src: '/storage/cms-media/events-slide-8-brahmotsavam.webp',
        alt: 'The planned Gaushala, temple, and healing environment',
        eyebrow: 'Planned \u00b7 Stage 2',
        title: 'Build the campus',
        body: 'On the secured land, the trust intends to build a Gaushala, a simple Shiva temple, and a disciplined healing environment. Construction begins after the land is acquired.',
    },
];
