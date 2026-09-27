/**
 * Per-category fallback prose for campaign pages.
 *
 * When a campaign row in the DB has no `description`, no `why_it_matters`,
 * or no `what_it_supports[]`, the Svelte layer falls back to these
 * category-keyed entries so the page never collapses and the viewer
 * always learns WHAT the campaign supports.
 *
 * Canon (see CONTENT_KNOWLEDGE_BASE.md):
 *   - One pooled fund spans daily school operations and the campus
 *     programme, including land acquisition and planned Gaushala, temple,
 *     healing-environment, and cow-care work as stages proceed.
 *   - Legacy categories remain readable but do not create separate
 *     giving destinations; the public Donate flow uses one canonical pool.
 *   - 80G and tax-deductibility claims are not asserted. The receipt
 *     section is honest: every donation gets an official receipt, and
 *     tax-deductibility is confirmed at the time of donation in line
 *     with applicable law.
 *   - Donor benefits (certificates, cow products) are described as
 *     future plans, not guarantees.
 */

export type CampaignCategory =
    | 'maintenance'
    | 'general'
    | 'diwali'
    | 'land_acquisition'
    | 'gaushala_build'
    | 'cow_care_future'
    | 'school_annadanam'
    | string; // forward-compat for future categories

export interface CampaignFallback {
    about: string;
    whyItMatters: string;
    whyItMattersBody: string;
    /** Short labels for the "What your offering supports" icon cards. */
    whatItSupports: string[];
}

const FALLBACKS: Record<string, CampaignFallback> = {
    land_acquisition: {
        about: 'Land acquisition is one stage within the pooled Schools and Campus Fund. The trust is working toward a proposed three-acre healing and service campus near Nanjangud, outside Mysore. The land is under discussion; construction follows once it is secured. Gifts support the pooled programme and are not designated to land alone.',
        whyItMatters: 'The land is the foundation. Every later stage depends on closing the acquisition first.',
        whyItMattersBody: 'Construction of the Gaushala, simple Shiva temple, and healing environment follows land acquisition. The pooled fund spans that campus sequence alongside daily school operations; a donor contributes to one shared fund rather than choosing an individual stage.',
        whatItSupports: [
            'Land acquisition near Nanjangud',
            'Due diligence and registration',
            'Site preparation for the design stage',
            'The foundation of the proposed campus',
        ],
    },
    gaushala_build: {
        about: 'The Gaushala and campus facilities are planned stages within the pooled Schools and Campus Fund. Work is sequenced with land acquisition and will proceed as the trust advances the proposed campus. These stages are not separate donation options.',
        whyItMatters: 'The buildings are how the land becomes a place where the work happens.',
        whyItMattersBody: 'Land without buildings is land; buildings are what turn it into a campus. The Gaushala will be a long-term home for the cows, not a short-term campaign. The temple will be the spiritual centre of the campus. The healing environment will be a disciplined, contemplative space — not a clinical facility, and not a guarantee of medical outcomes. The buildings are the practical expression of the trust\u2019s next chapter, and the trust will publish the design and the cost of each before any construction funds are accepted.',
        whatItSupports: [
            'Gaushala construction',
            'Simple Shiva temple',
            'Kamadhenu and Shiva-family shrines',
            'Healing environment facility',
        ],
    },
    cow_care_future: {
        about: 'Long-term cow care is included in the pooled campus programme as a planned stage after the Gaushala is built and operational. Future cow-related farming or products are intended possibilities, not guaranteed offerings, and this work is not a separate donation option.',
        whyItMatters: 'The cows are the long-term responsibility of the campus, not its opening ceremony.',
        whyItMattersBody: 'Care that continues across years is the difference between a project and a presence. The trust intends the Gaushala to be a long-term home for the cows, with the same discipline as the children\u2019s-care chapter. The cow-related farming and products for devotees, once operational, will be offered to supporters in accordance with the project\u2019s operating and legal framework. The trust will not promise guaranteed products, guaranteed delivery dates, or guaranteed quantities.',
        whatItSupports: [
            'Feed and veterinary care',
            'Cow-care staff',
            'Cow-related farming',
            'Future products for devotees',
        ],
    },
    school_annadanam: {
        about: 'Daily school operations are one part of the pooled Schools and Campus Fund. The trust runs schools where children in need of care — including blind and deaf pupils — receive free education. Annadanam provides meals, drinking water, and school events; gifts enter the shared fund rather than a school-only designation.',
        whyItMatters: 'The schools run today. Daily food, water, and care cannot wait for the campus.',
        whyItMattersBody: 'The schools operate today while the campus is planned. The pooled fund supports daily meals, water, and school events as well as the staged campus programme, with donations managed as one shared fund.',
        whatItSupports: [
            'Daily meals at the schools',
            'Drinking water for the children',
            'School events and gatherings',
            'Free education for blind and deaf pupils',
        ],
    },
    construction: {
        about: 'A planned stage of the campus sequence. The trust intends to construct the Shiva temple — the spiritual centre of the proposed healing and service campus — alongside the Gaushala, the Kamadhenu shrine, and a disciplined healing environment. These planned stages are included in the pooled Schools and Campus Fund, together with daily school operations and land acquisition. A gift is not restricted to this stage.',
        whyItMatters: 'The temple is how the land becomes a place where the work happens.',
        whyItMattersBody: 'The temple is part of the proposed campus programme. The pooled fund includes its planned construction alongside school operations, land acquisition, the Gaushala, healing environment, and cow care; donors do not select a separate building ledger.',
        whatItSupports: [
            'Shiva temple construction',
            'Kamadhenu and Shiva-family shrines',
            'Gaushala build programme',
            'Published cost and timeline',
        ],
    },
    operations: {
        about: 'Long-term cow care is a planned stage within the pooled Schools and Campus Fund. Once the campus is operational, the trust will focus on the welfare of cows at the Gaushala — feed, veterinary care, and the daily labour of a long-term shelter. This work is included in the shared fund alongside daily school operations and land acquisition. Future cow-related farming and products for devotees are intended benefits, not guaranteed offerings.',
        whyItMatters: 'The cows are the long-term responsibility of the campus, not its opening ceremony.',
        whyItMattersBody: 'Care that continues across years is the difference between a project and a presence. The trust intends the Gaushala to be a long-term home for the cows, held with the same discipline as the daily annadanam at the schools. The trust will not promise guaranteed products, guaranteed delivery dates, or guaranteed quantities.',
        whatItSupports: [
            'Feed and veterinary care',
            'Cow-care staff',
            'Long-term Gaushala shelter',
            'Future products for devotees',
        ],
    },
    maintenance: {
        about: 'The trust receives gifts through one pooled Schools and Campus Fund. The pool supports daily school operations and the staged campus programme; contributions are not separated into school, land, construction, or cow-care donation options.',
        whyItMatters: 'Most of the work is invisible — and all of it is essential.',
        whyItMattersBody: 'The trust\u2019s administrative work is what keeps the lights on and the records in order. It is the floor on which every other campaign stands. The general fund covers the people who coordinate donations, answer donor questions, maintain the records, and steward the trust\u2019s reporting. None of this is glamorous. All of it is essential.',
        whatItSupports: [
            'Administrative coordination',
            'Donor communications and receipts',
            'Records and reporting',
            'Operational overhead',
        ],
    },
    general: {
        about: 'The pooled Schools and Campus Fund supports daily school operations and the staged campus programme, including land acquisition and planned Gaushala, temple, healing-environment, and cow-care work. Donations are not restricted to a single sub-project, and the trust publishes how campaign funds were used when the campaign closes.',
        whyItMatters: 'One shared fund keeps school and campus giving coherent.',
        whyItMattersBody: 'Pooling keeps the donor-facing giving flow coherent: one destination supports daily school needs and the campus stages together. The trust manages allocation across these priorities and reports on the pooled campaign when it closes.',
        whatItSupports: [
            'Administrative staff',
            'Donor communications',
            'Records and reporting',
            'Audit and compliance',
        ],
    },
    diwali: {
        about: 'A seasonal campaign at the trust for a festival, an anniversary, or a community moment. Where the appeal supports school or campus work, gifts enter the pooled Schools and Campus Fund rather than a separate sub-project destination. The trust publishes how campaign funds were used when the campaign closes.',
        whyItMatters: 'Seasonal appeals are a way to mark the year alongside the work.',
        whyItMattersBody: 'The trust marks the year with devotional and community moments. A seasonal appeal is a chance to participate in one of those moments; if it supports school or campus work, it remains within the shared pooled fund. The trust publishes how campaign funds were used when the campaign closes.',
        whatItSupports: [
            'Seasonal celebration',
            'Devotional event funding',
            'Community participation',
            'Closing-report publication',
        ],
    },
};

/**
 * Returns the fallback for a category, or a generic one if the category
 * is unknown. The generic fallback is intentionally calm — it doesn't
 * invent specifics for a category the team hasn't defined.
 */
export function campaignFallbackFor(category: string): CampaignFallback {
    return (
        FALLBACKS[category] ?? {
            about: 'A trust programme supported through the pooled Schools and Campus Fund. Gifts support daily school operations and the staged campus programme, are not restricted to a single sub-project, and are acknowledged with an official receipt.',
            whyItMatters: 'Every contribution, of any size, sustains the trust\u2019s work.',
            whyItMattersBody: 'The trust runs on the generosity of its devotees and visitors. Every offering — of any size — joins one pooled fund for daily school operations and the staged campus programme. The trust publishes how pooled campaign funds were used when the campaign closes.',
            whatItSupports: [
                'Current campaign allocation',
                'Operational support',
                'Reporting and accountability',
                'The next chapter of the trust\u2019s work',
            ],
        }
    );
}

/**
 * The 3-step "how your offering becomes service" guide. Used on every
 * campaign page regardless of category — explains the donation flow
 * itself, not the campaign. Designed to walk the reader through the
 * giving process and hand them to the Donate CTA.
 *
 * 80G language is intentionally soft. Tax-deductibility is confirmed at
 * the time of donation in line with applicable law.
 */
export interface OfferingFlowStep {
    number: string;
    title: string;
    body: string;
}

export const OFFERING_FLOW: OfferingFlowStep[] = [
    {
        number: '01',
        title: 'Choose where your offering goes',
        body: 'Choose the one pooled Schools and Campus Fund. Donations support daily school operations and the staged campus programme, including land acquisition and planned Gaushala, temple, healing-environment, and cow-care work.',
    },
    {
        number: '02',
        title: 'Add a dedication, if you\u2019d like',
        body: 'Most offerings arrive quietly, without a name. If you wish, you can dedicate your offering in honour of a loved one, in memory of someone, or to mark a personal milestone — a wedding, a birth, the clearing of an obstacle. The dedication will appear on your receipt.',
    },
    {
        number: '03',
        title: 'Receive your official receipt',
        body: 'Within minutes of payment capture, you\u2019ll receive an emailed receipt at the address you provide. Tax-deductibility — including 80G certificates where applicable — is confirmed at the time of each donation in line with applicable law.',
    },
];

/**
 * Compact set of FAQ entries for the donation flow. Used on every
 * campaign show page regardless of category — these are questions
 * about the donation process itself, not the campaign.
 *
 * No 80G hard claim. No "test mode" dev-wire. The receipt is honest.
 */
export interface DonationFaq {
    question: string;
    answer: string;
}

export const DONATION_FAQ: DonationFaq[] = [
    {
        question: 'Where does my offering to this campaign go?',
        answer: 'Your offering enters one pooled Schools and Campus Fund. The trust allocates it across daily school operations and the staged campus programme, including land acquisition and planned Gaushala, temple, healing-environment, and cow-care work. The trust publishes how pooled campaign funds were used when the campaign closes.',
    },
    {
        question: 'When will I receive my official receipt?',
        answer: 'Receipts are generated automatically once the payment gateway confirms payment capture, which usually happens within minutes. The receipt is emailed to the address you provide, and a copy is available at the receipt link shown on the success page.',
    },
    {
        question: 'Is my donation eligible for a tax deduction?',
        answer: 'An official receipt is issued for every donation. Tax-deductibility — including 80G certificates where applicable — is confirmed at the time of each donation in line with applicable law. The trust will publish a dedicated legal page once it has been reviewed and approved.',
    },
    {
        question: 'Can I donate from outside India?',
        answer: 'International cards are supported through the gateway used at checkout. The amount will be converted to the campaign\u2019s currency at the gateway\u2019s prevailing rate, and the receipt will show the equivalent in the trust\u2019s base currency. PayPal support is on the roadmap.',
    },
    {
        question: 'Can I receive a certificate or a cow-related product for my donation?',
        answer: 'Donor certificates and cow-related products are intended future benefits, not guaranteed offerings. Once the campus is operational, the trust intends to develop them and may offer them to supporters in accordance with the project\u2019s operating and legal framework. The trust does not promise guaranteed products, guaranteed delivery dates, or guaranteed quantities.',
    },
];
