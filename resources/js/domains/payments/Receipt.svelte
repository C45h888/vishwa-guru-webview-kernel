<script lang="ts">
    import PublicLayout from '$shared/components/PublicLayout.svelte';
    import MandalaDecoration from '$shared/components/MandalaDecoration.svelte';
    import { Card, CardContent, CardHeader, CardTitle } from '$shared/ui/card';
    import { Badge } from '$shared/ui/badge';
    import { Button } from '$shared/ui/button';
    import Money from '$shared/components/Money.svelte';
    import { Download, ExternalLink, Shield, FileText } from 'lucide-svelte';
    import type { AppPageProps, ReceiptProps } from '$shared/lib/inertia';
    import SeoHead from '$shared/components/SeoHead.svelte';

    let {
        receipt,
        appName,
        appUrl,
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

<SeoHead title={`Receipt ${receipt.receipt_number}`} {appName} {appUrl} noindex />

<PublicLayout>
    <section class="relative overflow-hidden bg-background">
        <div
            class="pointer-events-none absolute -right-20 top-0 opacity-[0.08]"
            aria-hidden="true"
        >
            <MandalaDecoration size={320} tint="gold" />
        </div>

        <div class="container relative py-12 lg:py-20">
            <div class="mx-auto max-w-2xl space-y-6">
                <header class="space-y-3">
                    <p
                        class="text-xs font-semibold uppercase tracking-[0.25em] text-primary"
                    >
                        Official receipt
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1
                            class="font-serif text-3xl font-semibold lg:text-4xl"
                        >
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
                    <CardContent class="space-y-3 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-muted-foreground">Campaign</span>
                            <span class="text-right font-medium">
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
                            <span class="font-serif text-lg font-semibold">
                                <Money
                                    amountMinor={receipt.amount_minor}
                                    currencyCode={receipt.currency_code}
                                />
                            </span>
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
                            <span class="text-muted-foreground"
                                >Content hash</span
                            >
                            <span class="font-mono text-xs">{shortHash}…</span>
                        </div>
                        <div class="flex items-baseline justify-between">
                            <span class="text-muted-foreground"
                                >Issued at</span
                            >
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
                                <Download
                                    class="mr-1.5 h-4 w-4"
                                    aria-hidden="true"
                                />
                                {downloading
                                    ? 'Downloading…'
                                    : 'Download PDF'}
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
                            <p class="text-xs text-destructive">
                                {downloadError}
                            </p>
                        {/if}
                    </CardContent>
                </Card>

                <div
                    class="flex items-start gap-3 rounded-md border border-border/40 bg-ivory/60 p-4"
                >
                    <Shield
                        class="mt-0.5 h-5 w-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <div class="space-y-1">
                        <p class="text-sm font-medium">
                            Issued after gateway confirmation
                        </p>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            Receipts are issued only after the gateway webhook
                            confirms payment capture. The content hash above
                            matches the PDF you download.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</PublicLayout>
