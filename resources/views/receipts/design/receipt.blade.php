{{-- receipts/design/receipt.blade.php — THE canonical receipt document design. --}}
{{-- Data contract: DesignWorker::viewData() — typed fields from ReceiptDocument only. --}}
{{-- Design rules live in receipts/design/styles.blade.php. No logic beyond display conditionals. --}}

<div class="receipt-page">

    {{-- ── Header / donee identity ─────────────────────────────────── --}}
    <div class="header">
        <div class="trust-name">{{ $trust_name }}</div>
        @if($trust_address !== '')
            <div class="trust-line">{{ $trust_address }}</div>
        @endif
        @if($trust_email !== '' || $trust_phone !== '')
            <div class="trust-line">
                @if($trust_email !== ''){{ $trust_email }}@endif
                @if($trust_email !== '' && $trust_phone !== '') &nbsp;|&nbsp; @endif
                @if($trust_phone !== ''){{ $trust_phone }}@endif
            </div>
        @endif
        @if($trust_pan)
            <div class="trust-line">PAN: {{ $trust_pan }}</div>
        @endif
        <div class="receipt-title">Donation Receipt</div>
        <div class="receipt-fy">{{ $fy_label }}</div>
    </div>

    {{-- ── Receipt identity ────────────────────────────────────────── --}}
    <table class="meta-table" cellpadding="0" cellspacing="6">
        <tr>
            <td width="40%"><div class="meta-box">
                <div class="field-label">Receipt Number</div>
                <div class="field-value">{{ $receipt_number }}</div>
            </div></td>
            <td width="30%"><div class="meta-box">
                <div class="field-label">Date of Issue</div>
                <div class="field-value">{{ $issued_date }}</div>
            </div></td>
            <td width="30%"><div class="meta-box">
                <div class="field-label">Financial Year</div>
                <div class="field-value">{{ $fy_label }}</div>
            </div></td>
        </tr>
        <tr>
            <td><div class="meta-box">
                <div class="field-label">Payment Reference</div>
                <div class="field-value ref">{{ $payment_reference }}</div>
            </div></td>
            <td colspan="2"><div class="meta-box">
                <div class="field-label">Payment Date</div>
                <div class="field-value">{{ $payment_date }}</div>
            </div></td>
        </tr>
    </table>

    {{-- ── Amount ──────────────────────────────────────────────────── --}}
    <div class="amount-box">
        <div class="field-label">Donation Amount</div>
        <div class="amount-value">{{ $amount_display }}</div>
        @if($amount_in_words !== '')
            <div class="amount-words">{{ $amount_in_words }}</div>
        @endif
    </div>

    {{-- ── Donor ───────────────────────────────────────────────────── --}}
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
                    <div class="field-value">{{ $donor_email ?? '—' }}</div>
                </td>
            </tr>
            @if($donor_pan)
                <tr>
                    <td>
                        <div class="field-label">Donor PAN</div>
                        <div class="field-value" style="letter-spacing:1px;">{{ $donor_pan }}</div>
                    </td>
                    <td></td>
                </tr>
            @endif
        </table>

        @if($donor_address !== '')
            <div style="margin-top:8px;">
                <div class="field-label">Address</div>
                <div class="address-block">{{ $donor_address }}</div>
            </div>
        @endif
    </div>

    {{-- ── Campaign ────────────────────────────────────────────────── --}}
    @if($campaign_title !== '')
        <div class="section">
            <div class="section-title">Donation Towards</div>
            <div style="font-size:12px; margin-top:4px;">{{ $campaign_title }}</div>
        </div>
    @endif

    {{-- ── Installment schedule (corpus / pledge variant) ──────────── --}}
    {{-- Optional section: rendered only when the schedule is supplied. --}}
    @if(isset($installment_schedule) && is_array($installment_schedule) && count($installment_schedule) > 0)
        <div class="section">
            <div class="section-title">Installment Schedule</div>
            <table width="100%" cellpadding="5" cellspacing="0" style="border-collapse:collapse; margin-top:6px;">
                <thead>
                    <tr>
                        <th style="text-align:left;  font-size:9px; border-bottom:1px solid var(--receipt-rule);">Installment</th>
                        <th style="text-align:left;  font-size:9px; border-bottom:1px solid var(--receipt-rule);">Due Date</th>
                        <th style="text-align:right; font-size:9px; border-bottom:1px solid var(--receipt-rule);">Amount</th>
                        <th style="text-align:center; font-size:9px; border-bottom:1px solid var(--receipt-rule);">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($installment_schedule as $installment)
                        <tr>
                            <td style="font-size:10px; border-bottom:1px solid var(--receipt-rule);">{{ $installment['no'] }}</td>
                            <td style="font-size:10px; border-bottom:1px solid var(--receipt-rule);">{{ $installment['due_date'] }}</td>
                            <td style="font-size:10px; border-bottom:1px solid var(--receipt-rule); text-align:right;">{{ $installment['amount'] }}</td>
                            <td style="font-size:10px; border-bottom:1px solid var(--receipt-rule); text-align:center;">{{ $installment['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ── 80G certification block ─────────────────────────────────── --}}
    @if($tax_80g_eligible && $is_tax_deductible)
        <div class="tax-cert-box">
            <div class="tax-cert-title">Section 80G Certification</div>
            <div style="margin-top:5px;">
                This receipt certifies that the donation recorded above has been
                received by <strong>{{ $trust_name }}</strong>, registered under
                Section 80G of the Income Tax Act, 1961. The donor is entitled
                to a deduction subject to the conditions specified therein.
            </div>
            <div class="donee-grid">
                @if($trust_address !== '')
                    <div><span class="field-label">Donee Address</span><br>{{ $trust_address }}</div>
                @endif
                @if($trust_pan)
                    <div style="margin-top:3px;"><span class="field-label">Donee PAN</span><br>{{ $trust_pan }}</div>
                @endif
                @if($tax_80g_registration_number)
                    <div style="margin-top:3px;"><span class="field-label">80G Registration No.</span><br>{{ $tax_80g_registration_number }}</div>
                @endif
                @if($tax_80g_certificate_number)
                    <div style="margin-top:3px;"><span class="field-label">Certificate No.</span><br>{{ $tax_80g_certificate_number }}</div>
                @endif
            </div>
            @if($tax_80g_note)
                <div class="tax-note">{{ $tax_80g_note }}</div>
            @endif
        </div>
    @elseif($is_tax_deductible && $tax_80g_note)
        <div class="tax-cert-box">
            <div class="tax-cert-title">Tax Deduction Note</div>
            <div style="margin-top:5px;">{{ $tax_80g_note }}</div>
        </div>
    @endif

    {{-- ── Footer ──────────────────────────────────────────────────── --}}
    <div class="footer">
        <div>
            <strong>{{ $trust_name }}</strong>
            @if($trust_pan) &nbsp;|&nbsp; PAN: {{ $trust_pan }} @endif
            &nbsp;|&nbsp; Receipt {{ $receipt_number }}
            &nbsp;|&nbsp; Issued {{ $issued_date }}
        </div>
        <div class="verify-line">
            This is a computer-generated receipt and does not require a signature.
            Verify this receipt using the secure link provided in your donation confirmation email.
        </div>
    </div>

</div>
