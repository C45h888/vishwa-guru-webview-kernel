{{-- receipts/design/styles.blade.php — the receipt design system's style layer. --}}
{{-- Design-only file: consumed by DesignWorker::renderHtml(). No PHP logic here. --}}
{{-- Re-skin the receipt by editing ONLY this file's design tokens. --}}
<head>
<meta charset="UTF-8">
<title>Donation Receipt</title>
<style>
    /* ── Design tokens ─────────────────────────────────────────────── */
    :root {
        --receipt-ink:        #2b2118;
        --receipt-accent:     #8B0000;   /* deep temple red   */
        --receipt-gold:       #b98a2f;   /* accent gold       */
        --receipt-muted:      #6b6157;
        --receipt-rule:       #d8cfc2;
        --receipt-paper-tint: #faf7f1;   /* ivory             */
        --receipt-cert-tint:  #fffbea;
        --receipt-cert-edge:  #e6c200;
    }

    /* ── Base ─────────────────────────────────────────────────────── */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: "DejaVu Sans", Arial, sans-serif;
        font-size: 12px;
        color: var(--receipt-ink);
    }
    .receipt-page { padding: 24px 28px; }

    /* ── Header ───────────────────────────────────────────────────── */
    .header {
        text-align: center;
        border-bottom: 2px solid var(--receipt-accent);
        padding-bottom: 12px;
        margin-bottom: 18px;
    }
    .trust-name { font-size: 20px; font-weight: bold; color: var(--receipt-accent); letter-spacing: 0.5px; }
    .trust-line { font-size: 10px; color: var(--receipt-muted); margin-top: 3px; }
    .receipt-title {
        font-size: 15px; margin-top: 10px;
        letter-spacing: 3px; text-transform: uppercase; font-weight: bold;
    }
    .receipt-fy { font-size: 10px; color: var(--receipt-muted); margin-top: 4px; }

    /* ── Meta table ───────────────────────────────────────────────── */
    .meta-table { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
    .meta-box { background: var(--receipt-paper-tint); border: 1px solid var(--receipt-rule); padding: 7px 9px; }
    .field-label { font-size: 9px; color: var(--receipt-muted); text-transform: uppercase; letter-spacing: 0.6px; }
    .field-value { font-size: 12px; font-weight: bold; margin-top: 2px; }
    .field-value.ref { font-size: 10px; letter-spacing: 0.3px; word-break: break-all; }

    /* ── Amount box ───────────────────────────────────────────────── */
    .amount-box {
        background: var(--receipt-paper-tint);
        border: 1px solid var(--receipt-rule);
        border-top: 3px solid var(--receipt-gold);
        padding: 14px; text-align: center; margin: 14px 0;
    }
    .amount-value { font-size: 24px; font-weight: bold; color: var(--receipt-accent); margin-top: 4px; }
    .amount-words { font-size: 12px; color: var(--receipt-ink); margin-top: 5px; font-style: italic; }

    /* ── Sections ─────────────────────────────────────────────────── */
    .section { margin: 14px 0; }
    .section-title {
        font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px;
        color: var(--receipt-accent); border-bottom: 1px solid var(--receipt-accent);
        padding-bottom: 3px; margin-bottom: 8px; font-weight: bold;
    }
    .address-block { font-size: 11px; line-height: 1.55; white-space: pre-line; }

    /* ── 80G certificate block ────────────────────────────────────── */
    .tax-cert-box {
        background: var(--receipt-cert-tint);
        border: 1px solid var(--receipt-cert-edge);
        padding: 11px; margin-top: 12px; font-size: 10px; line-height: 1.6;
    }
    .tax-cert-title { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; }
    .donee-grid { margin-top: 6px; }
    .tax-note { color: var(--receipt-accent); font-size: 9px; margin-top: 5px; }

    /* ── Footer ───────────────────────────────────────────────────── */
    .footer {
        margin-top: 26px; padding-top: 10px;
        border-top: 1px solid var(--receipt-rule);
        font-size: 9px; color: var(--receipt-muted); text-align: center; line-height: 1.7;
    }
    .verify-line { margin-top: 4px; letter-spacing: 0.3px; }

    @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
