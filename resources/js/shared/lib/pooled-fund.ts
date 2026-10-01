/** Canonical public giving destination for the consolidated campaign. */
// Pinned by migration
// 2026_10_01_000001_k_campaigns_normalize_donate_pool_id.php. Keep in sync
// with config/campaigns.php (donation_pool_campaign_id).
export const POOLED_FUND_ID = 'campaign_01M3V9QP311Z564SCJ9GW47HE7';
export const POOLED_FUND_TITLE = 'Schools & Campus Pooled Fund';
export const POOLED_FUND_SHORT_DESCRIPTION =
    'One pooled fund for daily school support and the staged campus programme.';
export const POOLED_FUND_DESCRIPTION =
    'Donations are pooled by the trust across daily school operations and the staged campus programme near Nanjangud. The pooled fund includes land acquisition and planned Gaushala, temple, healing-environment, and cow-care work as those stages proceed. A gift is not restricted to a single sub-project. The trust publishes how campaign funds were used when the campaign closes.';

export function isPooledFund(campaign: { id?: string | null }): boolean {
    return campaign.id === POOLED_FUND_ID;
}
