<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '$shared/ui/card';
    import { Badge } from '$shared/ui/badge';
    import { Button } from '$shared/ui/button';
    import Money from '$shared/components/Money.svelte';
    import { Download, ExternalLink } from 'lucide-svelte';
    import type { AppPageProps, ReceiptProps } from '$shared/lib/inertia';

    let {
        receipt,
        appName,
    }: AppPageProps<{ receipt: ReceiptProps }> = $props();

    const shortHash = $derived(receipt.content_hash.slice(0, 12));
    const pdfUrl = $derived(`/receipts/${receipt.receipt_number}/download`);

    let downloading = $state(false);
    let downloadError = $state<string | null>(null);

    async function downloadPdf(): Promise<void> {
        downloading = true;
        downloadError = null;
        try {
            const response = await fetch(pdfUrl);
            if (!response.ok) {
                throw new Error(
                    `PDF download failed (HTTP ${response.status}).`,
                );
            }
            const blob = await response.blob();
            const blobUrl = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = `${receipt.receipt_number}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(blobUrl);
        } catch (err) {
            downloadError =
                err instanceof Error ? err.message : 'PDF download failed.';
        } finally {
            downloading = false;
        }
    }
</script>

<svelte:head>
    <title>Receipt {receipt.receipt_number} — {appName}</title>
</svelte:head>

<PublicLayout>
    <div class="mx-auto max-w-2xl space-y-6">
        <header class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-serif text-3xl font-semibold lg:text-4xl">
                    Receipt
                </h1>
                {#if receipt.tax_80g_eligible}
                    <Badge>80G eligible</Badge>
                {:else if receipt.is_tax_deductible}
                    <Badge variant="secondary">Tax deductible</Badge>
                {/if}
            </div>
            <p class="font-mono text-sm text-muted-foreground">
                {receipt.receipt_number}
            </p>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>Donation details</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <div class="flex items-baseline justify-between">
                    <span class="text-muted-foreground">Campaign</span>
                    <span class="font-medium">
                        {receipt.campaign_title_snapshot}
                    </span>
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
                    <Money
                        amountMinor={receipt.amount_minor}
                        currencyCode={receipt.currency_code}
                    />
                </div>
                {#if receipt.amount_in_words}
                    <p class="text-xs italic text-muted-foreground">
                        {receipt.amount_in_words}
                    </p>
                {/if}
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Integrity &amp; download</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <div class="flex items-baseline justify-between">
                    <span class="text-muted-foreground">Content hash</span>
                    <span class="font-mono text-xs">{shortHash}…</span>
                </div>
                <div class="flex items-baseline justify-between">
                    <span class="text-muted-foreground">Issued at</span>
                    <span>{receipt.generated_at}</span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-2 border-t border-border/60 pt-4"
                >
                    <Button
                        onclick={downloadPdf}
                        disabled={downloading}
                        size="sm"
                    >
                        <Download class="mr-1.5 h-4 w-4" aria-hidden="true" />
                        {downloading ? 'Downloading…' : 'Download PDF'}
                    </Button>
                    <Button
                        href={pdfUrl}
                        target="_blank"
                        rel="noopener"
                        variant="outline"
                        size="sm"
                    >
                        <ExternalLink
                            class="mr-1.5 h-4 w-4"
                            aria-hidden="true"
                        />
                        Open in new tab
                    </Button>
                </div>

                {#if downloadError}
                    <p class="text-xs text-destructive">{downloadError}</p>
                {/if}
            </CardContent>
        </Card>

        <p class="text-xs text-muted-foreground">
            Receipts are issued only after the gateway webhook confirms
            payment capture. The content hash above matches the PDF you
            download.
        </p>
    </div>
</PublicLayout>