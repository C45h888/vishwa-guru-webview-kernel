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
        alt: 'Varalakshmi Vratam — priest performing puja before a flower-adorned goddess',
        eyebrow: 'Festival',
        title: 'Varalakshmi Vratam',
        body: 'The worship of abundance — pooja, flowers, and the women\u2019s vow.',
    },
    {
        src: '/storage/cms-media/events-slide-7-cultural-evenings.webp',
        alt: 'Cultural evenings — classical Bharatanatyam performance',
        eyebrow: 'Culture',
        title: 'Cultural Evenings',
        body: 'Classical dance offered in devotion — Bharatanatyam under the temple lights.',
    },
    {
        src: '/storage/cms-media/events-slide-8-brahmotsavam.webp',
        alt: 'Brahmotsavam — the annual festival in its many moments',
        eyebrow: 'Annual',
        title: 'Brahmotsavam',
        body: 'The annual festival in its many moments — processions, honours, and song.',
    },
];
