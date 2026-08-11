import type { LegalPageContentProps } from '$shared/lib/inertia';

/**
 * Single source of fallback content for the Legal page.
 *
 * `LegalPageContentProps` gives a compile-time guarantee that every
 * field matches the wire contract. Svelte components import only this
 * module — they never duplicate copy in their own files.
 *
 * Reference numbers are explicit placeholders pending the trust
 * office confirmation — per Content KB §5 ("must not invent compliance
 * language"). Statutory descriptions cite the publicly known Income
 * Tax Act provisions; no invented claims are made.
 *
 * Certificates:
 *   - 80G  — enables donors to claim tax deduction under ITA 1961.
 *   - 12A  — confirms the trust's charitable registration and exemption.
 *   - POA  — Power of Attorney held by Sri Ram Ram Das Guruji since 2012.
 *   - TAN  — Tax Deduction and Collection Account Number for withholding compliance.
 */

export const FALLBACK_LEGAL_PAGE_CONTENT: LegalPageContentProps = {
    version: 1,

    intro: {
        eyebrow: 'Regulatory standing',
        title: "The trust's legal and tax-exempt status",
        body: "The trust's public standing rests on four registrations. Reference numbers below are placeholders until the trust office confirms them. Donors and auditors who need a certified copy of any document may contact the trust office through the contact page.",
    },

    certificates: [
        {
            certificate_key: 'eighty_g',
            title: '80G Certificate',
            reference_number: null,
            description:
                'Enables donors to claim a tax deduction for contributions to the trust under Section 80G of the Income Tax Act, 1961.',
            icon_key: 'eighty_g',
        },
        {
            certificate_key: 'twelve_a',
            title: '12A Registration',
            reference_number: null,
            description:
                'Confirms the trust’s registration as a charitable institution under Section 12A of the Income Tax Act, 1961, and its tax-exempt status.',
            icon_key: 'twelve_a',
        },
        {
            certificate_key: 'poa',
            title: 'Power of Attorney',
            reference_number: null,
            description:
                "Records the Power of Attorney by which Sri Ram Ram Das Guruji was authorised to act for the trust, received in 2012. This is the basis of Guruji’s authority over the trust’s public operations and finances.",
            icon_key: 'poa',
        },
        {
            certificate_key: 'tan',
            title: 'TAN',
            reference_number: null,
            description:
                'The Tax Deduction and Collection Account Number allotted to the trust for withholding-tax compliance under the Income Tax Act, 1961.',
            icon_key: 'tan',
        },
    ],
};
