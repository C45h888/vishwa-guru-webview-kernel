/**
 * Brand a string with a phantom prefix discriminator at the type level.
 *
 * Runtime cost: zero — branded types are erased by TypeScript after the
 * `& { readonly __prefix: P }` intersection is type-checked.
 *
 * Use case: distinguish IDs across entity boundaries. Without branding,
 * `CampaignSummaryProps.id` and `DonationIntentProps.campaign_id` are
 * both `string`; swapping them is a silent runtime bug. With branding,
 * the TypeScript compiler catches the mix-up at the assignment site.
 *
 * Example:
 *     type DonationId    = EntityId<'donation'>;
 *     type CampaignId   = EntityId<'campaign'>;
 *     const a: DonationId  = EntityId::generate('donation'); // OK
 *     const b: CampaignId  = a;                              // type error
 *
 * The PHP-side equivalent is App\Persistence\ValueObjects\EntityId,
 * which is the sole runtime enforcer of the {prefix}_{26-char-ULID}
 * format. The brand here is the TypeScript mirror.
 */
export type EntityId<P extends string> = string & {
    readonly __entity_id_prefix: P;
};