{{-- receipt-summary.blade.php — Plain-text email body fallback --}}
{{-- No CSS — compatible with Str::markdown() email rendering --}}

DONATION RECEIPT

Receipt Number: {{ $receipt_number }}
Issued: {{ $issued_date }}

Dear {{ $donor_name }},

Thank you for your generous donation.

Amount: {{ $currency_symbol }} {{ number_format($amount_minor / 100, 2) }}
{{ $amount_in_words ? strtoupper($amount_in_words) . ' RUPEES ONLY' : '' }}

Receipt Number: {{ $receipt_number }}
Date: {{ $issued_date }}

This is a computer-generated receipt. No signature required.
Verify your receipt at: {{ url('/receipts/' . $receipt_number) }}
