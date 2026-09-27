/**
 * EventHeroSlideshow — slide config.
 *
 * The events page is the "asset class" that showcases what the temple does
 * over the year. Three slides cover the breadth: cultural evenings, a
 * wedding ritual, and an indoor community gathering.
 *
 * All three picks are landscape (≈ 16:9 to 16:10) so they fill the
 * hero container cleanly under object-cover without the portrait-crop
 * artefact that the previous portrait picks caused on mobile.
 *
 * Images load directly from storage/app/public/cms-media-upscaled/canonical/.
 *
 * No backend coupling. When the events table is populated and the
 * EventsController can serve a server-driven slide list, this config can be
 * replaced by DB-backed slides without changing the component contract
 * (the prop is `{ src, eyebrow, title, body, alt }[]`).
 */
export interface EventSlide {
    src: string;
    alt: string;
    eyebrow: string;
    title: string;
    body: string;
}

const CANON = '/storage/cms-media-upscaled/canonical';

export const EVENT_SLIDES: EventSlide[] = [
    {
        src: `${CANON}/journal-cultural-04-literary-tribute.jpg`,
        alt: 'Cultural evening — literary tribute at the temple',
        eyebrow: 'Annual · cultural',
        title: 'Cultural evenings and literary tributes',
        body: 'The trust hosts cultural evenings and literary tributes throughout the year — classical performances, poetry readings, and the recognition of writers who sustain the temple’s cultural work.',
    },
    {
        src: `${CANON}/journal-kalyanam-03.jpg`,
        alt: 'Kalyanam — temple wedding ritual',
        eyebrow: 'Annual · weddings',
        title: 'Kalyanams at the temple',
        body: 'The temple hosts wedding rituals for the families of the surrounding villages. The kalyanam is a full-day affair — kalasham, deepam, the procession around the temple, and the dinner that follows.',
    },
    {
        src: `${CANON}/journal-indoor-01.jpg`,
        alt: 'Indoor gathering — community at the temple hall',
        eyebrow: 'Community · indoor',
        title: 'The hall gathers the community',
        body: 'Indoor events — school gatherings, community meetings, anniversary observances — fill the temple hall across the year. The trust provides the staging, the food, and the support that lets the hall host them.',
    },
];
