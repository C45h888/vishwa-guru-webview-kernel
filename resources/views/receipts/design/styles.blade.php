{{-- receipts/design/styles.blade.php — the receipt design system's style layer. --}}
{{-- Design-only file: consumed by DesignWorker::renderHtml(). No PHP logic here. --}}
{{-- Re-skin the receipt by editing ONLY this file's design tokens. --}}
{{-- Tokens converge with the web kernel (resources/css/app.css): saffron --}}
{{-- primary hsl(25 90% 48%), ivory paper, serif display headings, gold --}}
{{-- rule accents — so the PDF reads as the same trust on paper. --}}
<head>
<meta charset="UTF-8">
<title>Donation Receipt</title>
<style>
    /* ── Design tokens (web-kernel converged) ──────────────────────── */
    :root {
        --receipt-ink:        #2b2118;   /* warm temple ink            */
        --receipt-accent:     #c2570b;   /* saffron primary ≈ 25 90% 42% (print-safe depth) */
        --receipt-accent-deep:#8B0000;   /* deep temple red — seal/rule reserve */
        --receipt-gold:       #b98a2f;   /* mandala gold               */
        --receipt-muted:      #6b6157;
        --receipt-rule:       #d8cfc2;
        --receipt-paper-tint: #faf7f1;   /* ivory ≈ 42 50% 92%         */
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
    .serif { font-family: "DejaVu Serif", Georgia, serif; }

    /* ── Header ───────────────────────────────────────────────────── */
    .header {
        text-align: center;
        border-bottom: 3px double var(--receipt-gold);
        padding-bottom: 12px;
        margin-bottom: 18px;
    }
    .seal-row { margin-bottom: 6px; }
    .seal-row img { width: 64px; height: 64px; }
    .trust-name {
        font-family: "DejaVu Serif", Georgia, serif;
        font-size: 19px; font-weight: bold; color: var(--receipt-accent-deep);
        letter-spacing: 0.5px; line-height: 1.35;
    }
    .trust-line { font-size: 10px; color: var(--receipt-muted); margin-top: 3px; }
    .statutory-strip {
        margin-top: 8px; padding: 6px 8px;
        background: var(--receipt-paper-tint);
        border-top: 1px solid var(--receipt-rule);
        border-bottom: 1px solid var(--receipt-rule);
        font-size: 9px; color: var(--receipt-ink); line-height: 1.7;
    }
    .statutory-strip strong { color: var(--receipt-accent-deep); }
    .receipt-title {
        font-family: "DejaVu Serif", Georgia, serif;
        font-size: 16px; margin-top: 10px;
        letter-spacing: 3px; text-transform: uppercase; font-weight: bold;
        color: var(--receipt-accent);
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
        border-top: 3px solid var(--receipt-accent);
        padding: 14px; text-align: center; margin: 14px 0;
    }
    .amount-value { font-family: "DejaVu Serif", Georgia, serif; font-size: 24px; font-weight: bold; color: var(--receipt-accent-deep); margin-top: 4px; }
    .amount-words { font-size: 12px; color: var(--receipt-ink); margin-top: 5px; font-style: italic; }

    /* ── Sections ─────────────────────────────────────────────────── */
    .section { margin: 14px 0; }
    .section-title {
        font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px;
        color: var(--receipt-accent); border-bottom: 1px solid var(--receipt-gold);
        padding-bottom: 3px; margin-bottom: 8px; font-weight: bold;
    }
    .address-block { font-size: 11px; line-height: 1.55; white-space: pre-line; }
    .campaign-desc { font-size: 10.5px; color: var(--receipt-ink); line-height: 1.6; margin-top: 5px; }

    /* ── 80G certificate block ────────────────────────────────────── */
    .tax-cert-box {
        background: var(--receipt-cert-tint);
        border: 1px solid var(--receipt-cert-edge);
        padding: 11px; margin-top: 12px; font-size: 10px; line-height: 1.6;
    }
    .tax-cert-title { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; font-weight: bold; color: var(--receipt-accent-deep); }
    .donee-grid { margin-top: 6px; }
    .tax-note { color: var(--receipt-accent-deep); font-size: 9px; margin-top: 5px; }

    /* ── Signatory ────────────────────────────────────────────────── */
    .signatory { margin-top: 30px; width: 100%; }
    .signatory td { vertical-align: bottom; }
    .signature-img { max-height: 54px; }
    .sign-line { border-top: 1px solid var(--receipt-ink); margin-top: 4px; padding-top: 3px; font-size: 9px; color: var(--receipt-muted); }
    .seal-img { width: 76px; height: 76px; opacity: 0.92; }

    /* ── Footer ───────────────────────────────────────────────────── */
    .footer {
        margin-top: 18px; padding-top: 10px;
        border-top: 1px solid var(--receipt-rule);
        font-size: 9px; color: var(--receipt-muted); text-align: center; line-height: 1.7;
    }
    .verify-line { margin-top: 4px; letter-spacing: 0.3px; }
    .content-hash { font-size: 8px; color: var(--receipt-muted); word-break: break-all; margin-top: 5px; }

    @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
