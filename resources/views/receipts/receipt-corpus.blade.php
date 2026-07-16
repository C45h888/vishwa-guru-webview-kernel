{{-- receipt-corpus.blade.php — Multi-page variant for installment/corpus donations --}}
{{-- Used when a receipt has multi-year commitment or installment table --}}

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
        <div class="receipt-title">DONATION RECEIPT — CORPUS FUND</div>
        <div style="font-size:10px; color:#888; margin-top:4px;">{{ $issued_date_fy }}</div>
    </div>

    {{-- ── Receipt Identity ──────────────────────────────────────────── --}}
    <table width="100%" cellpadding="4" cellspacing="0" style="margin-bottom:16px; background:#f9f9f9; border:1px solid #ddd;">
        <tr>
            <td width="33%">
                <div class="field-label">Receipt Number</div>
                <div class="field-value" style="font-size:14px; letter-spacing:1px;">{{ $receipt_number }}</div>
            </td>
            <td width="33%">
                <div class="field-label">Date of Issue</div>
                <div class="field-value">{{ $issued_date_formatted }}</div>
            </td>
            <td width="34%">
                <div class="field-label">FY</div>
                <div class="field-value">{{ $issued_date_fy }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="field-label">Payment Ref.</div>
                <div class="field-value" style="font-size:10px;">{{ $payment_id }}</div>
            </td>
            <td>
                <div class="field-label">Payment Date</div>
                <div class="field-value">{{ $payment_date }}</div>
            </td>
            <td>
                <div class="field-label">Payment Mode</div>
                <div class="field-value">{{ $payment_method ?? 'Others' }}</div>
            </td>
        </tr>
    </table>

    {{-- ── Amount ──────────────────────────────────────────────────────── --}}
    <div class="amount-box" style="background:#fffbea; border-color:#e6c200;">
        <div class="field-label" style="text-align:center; margin-bottom:6px;">
            TOTAL DONATION — CORPUS FUND
        </div>
        <div class="amount-value" style="color:#333;">
            {{ $currency_symbol }}&nbsp;{{ number_format($amount_minor / 100, 2) }}
        </div>
        @if($amount_in_words)
            <div class="amount-words">{{ $amount_in_words }}</div>
        @endif
    </div>

    {{-- ── Donor Details ──────────────────────────────────────────────── --}}
    <div class="section">
        <div class="section-title">Donor Particulars</div>
        <table width="100%" cellpadding="4" cellspacing="0">
            <tr>
                <td width="60%">
                    <div class="field-label">Name of the Donor</div>
                    <div class="field-value">{{ $donor_name }}</div>
                </td>
                <td width="40%">
                    <div class="field-label">Email</div>
                    <div class="field-value" style="font-size:12px;">{{ $donor_email ?? '—' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="field-label">PAN</div>
                    <div class="field-value" style="letter-spacing:1px;">
                        {{ $donor_pan ?? 'NOT PROVIDED' }}
                    </div>
                </td>
                <td></td>
            </tr>
        </table>

        @if($donor_address && $donor_address !== '')
            <div style="margin-top:8px;">
                <div class="field-label">Address</div>
                <div class="address-block">{{ $donor_address }}</div>
            </div>
        @endif
    </div>

    {{-- ── Corpus Commitment ─────────────────────────────────────────────── --}}
    @if(isset($installment_schedule) && is_array($installment_schedule))
        <div class="section">
            <div class="section-title">Installment Schedule</div>
            <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse; margin-top:8px;">
                <thead>
                    <tr style="background:#f0f0f0;">
                        <th style="text-align:left; font-size:10px; border-bottom:1px solid #ccc;">Installment</th>
                        <th style="text-align:left; font-size:10px; border-bottom:1px solid #ccc;">Due Date</th>
                        <th style="text-align:right; font-size:10px; border-bottom:1px solid #ccc;">Amount</th>
                        <th style="text-align:center; font-size:10px; border-bottom:1px solid #ccc;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($installment_schedule as $installment)
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="font-size:11px; padding:4px 0;">{{ $installment['no'] }}</td>
                            <td style="font-size:11px; padding:4px 0;">{{ $installment['due_date'] }}</td>
                            <td style="font-size:11px; padding:4px 0; text-align:right;">{{ $installment['amount'] }}</td>
                            <td style="font-size:11px; padding:4px 0; text-align:center;">{{ $installment['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ── 80G ────────────────────────────────────────────────────────── --}}
    @if($tax_80g_eligible && $is_tax_deductible)
        <div class="tax-cert-box">
            <strong>Section 80G Certificate</strong><br>
            <span style="font-size:10px;">
                This receipt certifies that the above donation has been received by
                <strong>{{ $trust_name }}</strong>, registered under Section 80G of the Income Tax Act, 1961
                (Registration No: {{ $tax_80g_certificate_number ?? 'PENDING' }}).
                The donor is entitled to a deduction subject to conditions specified therein.
            </span>
        </div>
    @endif

    {{-- ── Footer ─────────────────────────────────────────────────────── --}}
    <div class="footer" style="margin-top:60px;">
        <div>
            <strong>{{ $trust_name }}</strong>
            &nbsp;|&nbsp; {{ $trust_address }}
            &nbsp;|&nbsp; PAN: {{ $trust_pan ?? 'N/A' }}
        </div>
        <div style="margin-top:4px;">
            Receipt {{ $receipt_number }} &nbsp;|&nbsp;
            Issued {{ $issued_date_formatted }} &nbsp;|&nbsp;
            Content Hash: {{ substr($content_hash ?? '', 0, 12) }}...
        </div>
    </div>

</div>
