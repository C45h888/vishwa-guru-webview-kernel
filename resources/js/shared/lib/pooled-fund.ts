/** Canonical public giving destination for the consolidated campaign. */
export const POOLED_FUND_ID = 'campaign_general_fund_2026';
export const POOLED_FUND_TITLE = 'Schools & Campus Pooled Fund';
export const POOLED_FUND_SHORT_DESCRIPTION =
    'One pooled fund for daily school support and the staged campus programme.';
export const POOLED_FUND_DESCRIPTION =
    'Donations are pooled by the trust across daily school operations and the staged campus programme near Nanjangud. The pooled fund includes land acquisition and planned Gaushala, temple, healing-environment, and cow-care work as those stages proceed. A gift is not restricted to a single sub-project. The trust publishes how campaign funds were used when the campaign closes.';

export function isPooledFund(campaign: { id?: string | null }): boolean {
    return campaign.id === POOLED_FUND_ID;
}
