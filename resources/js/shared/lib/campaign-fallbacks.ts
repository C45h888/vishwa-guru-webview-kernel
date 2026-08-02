/**
 * Per-category fallback prose for campaign pages.
 *
 * When a campaign row in the DB has no `description`, no `why_it_matters`,
 * or no `what_it_supports[]`, the Svelte layer falls back to these
 * category-keyed entries so the page never collapses and the viewer
 * always learns WHAT the campaign supports.
 *
 * Voice: present-tense, contemplative, specific. Matches the editorial
 * tone established in `homepage-fallbacks.ts`. The About copy runs 4
 * sentences — substantial enough to explain the work, light enough that
 * it doesn't bury the donate CTA.
 *
 * Phase 3 (copy finalisation) is the right time to refine this prose
 * with the temple's own voice — these are placeholders, not the
 * canonical copy.
 */

export type CampaignCategory =
    | 'maintenance'
    | 'general'
    | 'diwali'
    | string; // forward-compat for future categories

export interface CampaignFallback {
    about: string;
    whyItMatters: string;
    whyItMattersBody: string;
    /** Short labels for the "What your offering supports" icon cards. */
    whatItSupports: string[];
}

const FALLBACKS: Record<string, CampaignFallback> = {
    maintenance: {
        about: 'Sustaining the daily rhythms of pooja, annadanam, and the care of the temple structure. The work is largely invisible — repointing the prakara wall before the monsoon, replacing the worn stone at the threshold, repairing the lamp-stands whose brass has thinned across decades of light. We use traditional materials where we can: lime mortar over cement, hand-cut stone over machine-cut, oil-resin over polyurethane. Craftspeople from Coimbatore and Velliangiri have been doing this work for generations; the campaign supports their livelihoods as much as the structure itself.',
        whyItMatters: 'A temple that stands is a temple that can keep standing.',
        whyItMattersBody: 'A structure is not the practice — but the practice cannot continue without the structure that holds it. Every repair, every replacement, every maintenance cycle ensures that tomorrow’s pooja finds the temple ready, the lamp-stand in place, the floor sound under the devotee’s feet. The campaign exists so that the rhythm of daily worship never has to pause for want of a wall, a roof, or a stone.',
        whatItSupports: [
            'Daily pooja materials',
            'Sanctum and hall repairs',
            'Grounds care and cleaning',
            'Long-term preservation',
        ],
    },
    general: {
        about: 'Supporting the temple’s day-to-day operations — the people and provisions that keep every other campaign possible. The general fund covers the salaries of temple staff: the priests who keep the lamp lit across the three poojas, the cooks who serve annadanam, the cleaners who arrive before sunrise. It also covers utilities, kitchen supplies, the cost of a kilogram of rice, the soap at the bathroom tap. None of this is glamorous. All of it is essential. When the general fund is healthy, every specific campaign can focus on its own work — the maintenance, the festival, the initiative.',
        whyItMatters: 'Most of the temple’s work is invisible — and all of it is essential.',
        whyItMattersBody: 'Visitors see pooja and the lamps; they don’t see the salary that brings the cook to the kitchen, or the wages that bring the sweeper to the prakara at 4am, or the cost of the bucket of rice that becomes a thousand meals. The general fund keeps the entire body of the temple working — the people who clean, cook, serve, ring the bell, light the lamp, maintain the records, and answer the door. Without it, every specific campaign would have to fund the operations as well as the cause. The general fund is the floor on which every other campaign stands.',
        whatItSupports: [
            'Priests and temple staff',
            'Annadanam kitchen',
            'Utilities and supplies',
            'Volunteer support',
        ],
    },
    diwali: {
        about: 'Lighting lamps across the temple for the festival of Diwali — an offering to all who visit. The week of Diwali expands the temple’s rhythms: extra poojas at dawn and dusk, a longer annadanam that runs through the evening, decorations of fresh flowers and rangoli in the prakara, sweets distributed to every visitor who arrives. The campaign funds the special poojas, the ghee and wicks for thousands of lamps, the sugar and ghee for the prasadam, the additional provisions for the kitchen, and the craftspeople who set up and dismantle the decorations. Diwali at the temple is run entirely on contributions — there is no institutional budget for it.',
        whyItMatters: 'The lamps that burn across the temple on this night are an offering, not a decoration.',
        whyItMattersBody: 'Diwali is the festival of light over darkness. The lamps lit across the temple on this night are not for the building; they are for every person who enters the prakara, for every family that arrives with a plate of sweets to offer, for every child seeing the diyas for the first time. The campaign funds the entire week’s offering — the lamps, the sweets, the flowers, the meals, the handovering of light from one lamp to another in a chain that goes on through the night.',
        whatItSupports: [
            'Special poojas and homas',
            'Prasadam and sweets',
            'Temple decoration',
            'Extended annadanam',
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
            about: 'A current campaign at the temple — every contribution sustains the daily rhythms of pooja, annadanam, and the care of the temple.',
            whyItMatters: 'Every contribution, of any size, sustains the temple’s daily work.',
            whyItMattersBody: 'The temple runs entirely on the generosity of its devotees and visitors. Every offering — of any size — joins the work that keeps pooja, annadanam, and the care of the structure going. The trust treats each campaign as its own ledger and publishes how the offerings were spent when each one closes.',
            whatItSupports: [
                'Daily pooja materials',
                'Temple operations',
                'The care of the structure',
                'The broader work of the trust',
            ],
        }
    );
}

/**
 * The 3-step "how your offering becomes seva" guide. Used on every
 * campaign page regardless of category — explains the donation flow
 * itself, not the campaign. Designed to walk the reader through the
 * giving process and hand them to the Donate CTA.
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
        body: 'Pick a specific campaign — or give to the general fund. The general fund keeps the temple running day to day; campaign funds go to a named cause with its own ledger, with progress visible on every page.',
    },
    {
        number: '02',
        title: 'Add a dedication, if you’d like',
        body: 'Most offerings arrive quietly, without a name. If you wish, you can dedicate your offering in honour of a loved one, in memory of someone, or to mark a personal milestone — a wedding, a birth, the clearing of an obstacle. The dedication will appear on your receipt.',
    },
    {
        number: '03',
        title: 'Receive your receipt and 80G certificate',
        body: 'Within minutes of payment capture, you’ll receive an emailed receipt at the address you provide. For donations above the statutory threshold, the 80G tax certificate arrives within seven working days, signed by the trust and registered with the income-tax department.',
    },
];

/**
 * Compact set of FAQ entries for the donation flow. Used on every
 * campaign show page regardless of category — these are questions
 * about the donation process itself, not the campaign.
 */
export interface DonationFaq {
    question: string;
    answer: string;
}

export const DONATION_FAQ: DonationFaq[] = [
    {
        question: 'Is this a live payment?',
        answer: 'The temple is currently in Razorpay test mode. Your card will not be charged for real amounts during this preview — any number you see on a receipt is a test transaction. The trust will move to live payments before the public launch.',
    },
    {
        question: 'When will I receive an official receipt?',
        answer: 'Receipts are generated automatically once the gateway confirms payment capture, which usually happens within minutes. The receipt is emailed to the address you provide, and a copy is always available at /receipts/{number} for the trustee to retrieve on request.',
    },
    {
        question: 'Is my donation eligible for an 80G tax deduction?',
        answer: 'Yes. Donations above the statutory threshold receive an official 80G certificate, mailed within seven working days. The certificate carries the trust’s registration number, the donation amount, and a content hash for verification.',
    },
    {
        question: 'Can I donate from outside India?',
        answer: 'International cards are supported through Razorpay’s standard checkout. The amount will be converted to the campaign’s currency at the gateway’s prevailing rate, and the receipt will show the equivalent in the temple’s base currency. PayPal support is on the roadmap.',
    },
];
