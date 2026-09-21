/**
 * Per-category fallback prose for campaign pages.
 *
 * When a campaign row in the DB has no `description`, no `why_it_matters`,
 * or no `what_it_supports[]`, the Svelte layer falls back to these
 * category-keyed entries so the page never collapses and the viewer
 * always learns WHAT the campaign supports.
 *
 * Canon (see CONTENT_KNOWLEDGE_BASE.md):
 *   - Two current causes: daily school operations (annadanam — food,
 *     water, school events for blind and deaf pupils on free education)
 *     and the campus land fund. Campus construction and cow care are
 *     planned stages that open later.
 *   - The three fundraising stages land → construction → cow care still
 *     describe the CAMPUS sequence; the school cause sits alongside as
 *     an ongoing ask. The categories below mirror that combined picture
 *     even when the legacy campaign rows still use the old keys
 *     (`maintenance`, `general`, `diwali`); the new prose tells the
 *     truth about what the trust is doing today.
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
        about: 'One of two current causes. The trust is acquiring land near Nanjangud, outside Mysore, for a proposed three-acre healing and service campus. The land is under discussion; the acquisition requires additional capital. Until the land is secured, no construction begins. Contributions to this campaign sit alongside daily annadanam at the schools — pick the cause you want to sustain.',
        whyItMatters: 'The land is the foundation. Every later stage depends on closing the acquisition first.',
        whyItMattersBody: 'Construction of the Gaushala, the simple Shiva temple, the Kamadhenu shrine, and the healing environment cannot begin until the land is secured. The trust runs two current causes in parallel — daily annadanam at the schools and this land fund — so contributors pick the cause they want to sustain. Contributors to this campaign fund the single most concrete step of the campus sequence.',
        whatItSupports: [
            'Land acquisition near Nanjangud',
            'Due diligence and registration',
            'Site preparation for the design stage',
            'The foundation of the proposed campus',
        ],
    },
    gaushala_build: {
        about: 'A planned future stage. Once the land is secured, the trust intends to construct the Gaushala, a simple Shiva temple, the Kamadhenu shrine, and a disciplined healing environment for people recovering from addiction or serious personal difficulty. This stage is not yet fundraising; it will open after the land acquisition closes. The trust will publish the construction timeline and the expected cost of each building before contributions are requested.',
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
        about: 'A planned future stage. Once the campus is operational, the trust will focus on the long-term care and welfare of the cows resident at the Gaushala. Future plans include cow-related farming and products for devotees, in accordance with the project\u2019s operating and legal framework. These are intended future benefits, not guaranteed offerings. This stage is not yet fundraising.',
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
        about: 'One of two current causes. The trust runs schools where children in need of care — including blind and deaf pupils — receive free education. Every school day the trust provides daily annadanam: meals, drinking water, and events at the schools. Contributions to this cause sustain those daily operations, openly and accountably, alongside the campus land fund.',
        whyItMatters: 'The schools run today. Daily food, water, and care cannot wait for the campus.',
        whyItMattersBody: 'Land without children fed today is only a plan. The schools need meals, water, and events every school day — this is the trust\u2019s daily ask, running in parallel with the land fund. Contributors to this cause sustain the daily operations of the schools, openly and accountably.',
        whatItSupports: [
            'Daily meals at the schools',
            'Drinking water for the children',
            'School events and gatherings',
            'Free education for blind and deaf pupils',
        ],
    },
    construction: {
        about: 'A planned stage of the campus sequence. Once the land near Nanjangud is secured, the trust intends to construct the Shiva temple — the spiritual centre of the proposed healing and service campus — alongside the Gaushala, the Kamadhenu shrine, and a disciplined healing environment. This stage opens after the land fund closes; the schools\u2019 daily annadanam runs in parallel today. The trust will publish the construction timeline and expected costs before contributions are requested.',
        whyItMatters: 'The temple is how the land becomes a place where the work happens.',
        whyItMattersBody: 'Land without buildings is only a plan; the temple will be the spiritual centre of the campus, raised with the same discipline as the schools. The trust runs two current causes in parallel — daily annadanam at the schools and the land fund — and this construction stage opens next. Contributors here fund a named, published building step, not an open pool.',
        whatItSupports: [
            'Shiva temple construction',
            'Kamadhenu and Shiva-family shrines',
            'Gaushala build programme',
            'Published cost and timeline',
        ],
    },
    operations: {
        about: 'A planned stage of the campus sequence. Once the campus is operational, the trust will focus on the long-term care and welfare of the cows resident at the Gaushala — feed, veterinary care, and the daily labour of a long-term shelter. This stage opens after construction; the schools\u2019 daily annadanam runs in parallel today. Future cow-related farming and products for devotees are intended benefits, not guaranteed offerings.',
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
        about: 'A current campaign at the trust. Alongside the two named causes — daily annadanam at the schools and the campus land fund — the trust\u2019s day-to-day administrative and operational costs are met by general-fund contributions. Every donation is acknowledged with an official receipt, and the trust publishes how funds are spent when each campaign closes.',
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
        about: 'A current campaign at the trust. General-fund contributions support the administrative backbone of the trust\u2019s work — the people and the systems that keep the two named causes (daily annadanam at the schools and the campus land fund) possible. The trust allocates general-fund spending transparently and reports how funds were used when each campaign closes.',
        whyItMatters: 'The general fund is what lets every specific campaign focus on its own work.',
        whyItMattersBody: 'When the general fund is healthy, every specific cause can focus on its own work — daily annadanam at the schools, the land fund, the construction, the cow care. When the general fund is weak, every specific campaign has to fund the administration as well as the cause. The trust\u2019s preference is to keep these streams separate, so that each donor can see exactly what their contribution enabled.',
        whatItSupports: [
            'Administrative staff',
            'Donor communications',
            'Records and reporting',
            'Audit and compliance',
        ],
    },
    diwali: {
        about: 'A seasonal campaign at the trust. The trust occasionally runs a seasonal appeal — for a festival, an anniversary, or a community moment. Each seasonal appeal is its own ledger with a clear goal, timeline, and use for every rupee given. The trust publishes how the funds were spent when each seasonal appeal closes.',
        whyItMatters: 'Seasonal appeals are a way to mark the year alongside the work.',
        whyItMattersBody: 'The trust marks the year with devotional and community moments. A seasonal appeal is a chance to participate in one of those moments — clearly scoped, clearly led, and clearly closed when the appeal ends. The trust treats each seasonal appeal as its own ledger, with progress visible on every page and a closing report when the campaign ends.',
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
            about: 'A current campaign at the trust. Every contribution is acknowledged with an official receipt, and the trust publishes how funds are spent when each campaign closes.',
            whyItMatters: 'Every contribution, of any size, sustains the trust\u2019s work.',
            whyItMattersBody: 'The trust runs on the generosity of its devotees and visitors. Every offering — of any size — joins the work that keeps the next chapter of the trust\u2019s service going. The trust treats each campaign as its own ledger and publishes how the offerings were spent when each one closes.',
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
        body: 'Pick a specific cause — daily annadanam at the schools, the campus land fund, or the general fund. The general fund keeps the trust\u2019s administration running; cause funds go to a named purpose with its own ledger, with progress visible on every page.',
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
        answer: 'Your offering goes to the campaign you choose — daily annadanam at the schools, the campus land fund, or the general fund — each kept as its own ledger. When a campaign closes, the trust publishes how the offerings were spent.',
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
