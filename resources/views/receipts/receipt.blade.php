{{-- Receipt.blade.php — Single-page A4 receipt --}}
{{-- Data fields provided by ReceiptFormatter::format() --}}

<div class="receipt-page">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="header">
        <div class="trust-name">{{ $trust_name }}</div>
        @if($trust_address)
            <div style="font-size:11px; color:#555; margin-top:4px;">{{ $trust_address }}</div>
        @endif
        @if($trust_email)
            <div style="font-size:11px; color:#555;">{{ $trust_email }}</div>
        @endif
        <div class="receipt-title">DONATION RECEIPT</div>
        <div style="font-size:10px; color:#888; margin-top:4px;">{{ $issued_date_fy }}</div>
    </div>

    {{-- ── Receipt Meta ─────────────────────────────────────────────────── --}}
    <table width="100%" cellpadding="4" cellspacing="0" style="margin-bottom:16px;">
        <tr>
            <td width="50%">
                <div class="field-label">Receipt Number</div>
                <div class="field-value">{{ $receipt_number }}</div>
            </td>
            <td width="50%">
                <div class="field-label">Date of Issue</div>
                <div class="field-value">{{ $issued_date_formatted }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="field-label">Payment Reference</div>
                <div class="field-value" style="font-size:11px;">{{ $payment_id }}</div>
            </td>
            <td>
                <div class="field-label">Payment Date</div>
                <div class="field-value">{{ $payment_date }}</div>
            </td>
        </tr>
    </table>

    {{-- ── Amount Box ────────────────────────────────────────────────────── --}}
    <div class="amount-box">
        <div class="field-label" style="text-align:center; margin-bottom:6px;">DONATION AMOUNT</div>
        <div class="amount-value">{{ $currency_symbol }}&nbsp;{{ number_format($amount_minor / 100, 2) }}</div>
        @if($amount_in_words)
            <div class="amount-words">{{ $amount_in_words }}</div>
        @endif
    </div>

    {{-- ── Donor Info ───────────────────────────────────────────────────── --}}
    <div class="section">
        <div class="section-title">Donor Details</div>
        <table width="100%" cellpadding="4" cellspacing="0">
            <tr>
                <td width="50%">
                    <div class="field-label">Name</div>
                    <div class="field-value">{{ $donor_name }}</div>
                </td>
                <td width="50%">
                    <div class="field-label">Email</div>
                    <div class="field-value" style="font-size:12px;">{{ $donor_email ?? '—' }}</div>
                </td>
            </tr>
            @if($donor_pan)
                <tr>
                    <td>
                        <div class="field-label">Donor PAN</div>
                        <div class="field-value" style="font-size:12px; letter-spacing:1px;">{{ $donor_pan }}</div>
                    </td>
                    <td></td>
                </tr>
            @endif
        </table>

        @if($donor_address && $donor_address !== '')
            <div style="margin-top:8px;">
                <div class="field-label">Address</div>
                <div class="address-block">{{ $donor_address }}</div>
            </div>
        @endif
    </div>

    {{-- ── Campaign ─────────────────────────────────────────────────────── --}}
    @if(isset($campaign_title) && $campaign_title)
        <div class="section">
            <div class="section-title">Donation Towards</div>
            <div style="font-size:12px; margin-top:4px;">{{ $campaign_title }}</div>
        </div>
    @endif

    {{-- ── 80G Certificate ─────────────────────────────────────────────── --}}
    @if($tax_80g_eligible && $is_tax_deductible)
        <div class="tax-cert-box">
            <strong>80G Certificate</strong><br>
            <span style="font-size:10px;">
                This donation is eligible for deduction under Section 80G of the
                Income Tax Act, 1961.
                @if($tax_80g_certificate_number)
                    &nbsp;Certificate No: <strong>{{ $tax_80g_certificate_number }}</strong>
                @endif
            </span>
            @if($pan_required_note)
                <br><span style="color:#c00; font-size:10px;">{{ $pan_required_note }}</span>
            @endif
        </div>
    @endif

    {{-- ── Footer ────────────────────────────────────────────────────────── --}}
    <div class="footer">
        <div style="margin-bottom:8px;">
            @if($trust_pan)
                <span>PAN: {{ $trust_pan }}</span>
                &nbsp;|&nbsp;
            @endif
            <span>Generated: {{ $issued_date_formatted }}</span>
            &nbsp;|&nbsp;
            <span>{{ $receipt_number }}</span>
        </div>
        <div>
            This is a computer-generated receipt and does not require a signature.
            Verify at {{ url('/') }}
        </div>
    </div>

</div>
