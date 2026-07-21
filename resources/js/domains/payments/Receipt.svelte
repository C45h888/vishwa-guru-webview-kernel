<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '$shared/ui/card';
    import { Badge } from '$shared/ui/badge';
    import Money from '$shared/components/Money.svelte';
    import type { AppPageProps } from '$shared/lib/inertia';

    /**
     * Shape coming from ReceiptController (Receipt::toArray()).
     * Kept narrow on purpose — only fields the public UI displays.
     */
    interface ReceiptSummaryProps {
        receipt_number: string;
        campaign_title_snapshot: string;
        donor_name: string;
        donor_email: string | null;
        amount_minor: number;
        currency_code: string;
        amount_in_words: string | null;
        is_tax_deductible: boolean;
        tax_80g_eligible: boolean;
        content_hash: string;
        state: string;
        generated_at: string;
    }

    let {
        receipt,
        appName,
    }: AppPageProps<{ receipt: ReceiptSummaryProps }> = $props();

    const shortHash = $derived(receipt.content_hash.slice(0, 12));
</script>

<svelte:head>
    <title>Receipt {receipt.receipt_number} — {appName}</title>
</svelte:head>

<PublicLayout>
    <div class="mx-auto max-w-2xl space-y-6">
        <header class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-3xl font-semibold">Receipt</h1>
                {#if receipt.tax_80g_eligible}
                    <Badge>80G eligible</Badge>
                {:else if receipt.is_tax_deductible}
                    <Badge variant="secondary">Tax deductible</Badge>
                {/if}
            </div>
            <p class="font-mono text-sm text-muted-foreground">{receipt.receipt_number}</p>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>Donation details</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <div class="flex items-baseline justify-between">
                    <span class="text-muted-foreground">Campaign</span>
                    <span class="font-medium">{receipt.campaign_title_snapshot}</span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span class="text-muted-foreground">Donor</span>
                    <span class="font-medium">{receipt.donor_name}</span>
                </div>
                {#if receipt.donor_email}
                    <div class="flex items-baseline justify-between">
                        <span class="text-muted-foreground">Email</span>
                        <span>{receipt.donor_email}</span>
                    </div>
                {/if}
                <div class="flex items-baseline justify-between">
                    <span class="text-muted-foreground">Amount</span>
                    <Money amountMinor={receipt.amount_minor} currencyCode={receipt.currency_code} />
                </div>
                {#if receipt.amount_in_words}
                    <p class="text-xs italic text-muted-foreground">{receipt.amount_in_words}</p>
                {/if}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Integrity</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <div class="flex items-baseline justify-between">
                    <span class="text-muted-foreground">Content hash</span>
                    <span class="font-mono text-xs">{shortHash}…</span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span class="text-muted-foreground">Issued at</span>
                    <span>{receipt.generated_at}</span>
                </div>
            </CardContent>
        </Card>

        <p class="text-xs text-muted-foreground">
            Receipts are issued only after the gateway webhook confirms payment capture. Download links to the file-backed receipt PDF are wired in a future pass.
        </p>
    </div>
</PublicLayout>
