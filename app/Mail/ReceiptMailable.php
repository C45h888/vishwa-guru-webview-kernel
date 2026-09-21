<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Receipt mail — sent by ReceiptEmailJob.
 *
 * Implements Mailable (not Mail::raw) so Mail::fake() and Mail::assertSent()
 * track it correctly in tests, and so future work can swap plain-text for
 * HTML + attachment by changing build() rather than the call site.
 *
 * Doctrine:
 *   - No PII (PAN, address) in the body — the email points donors to the
 *     receipt page where the access-token-gated PDF download lives.
 *   - Subject + body are templated here so ReceiptEmailJob stays thin.
 */
final class ReceiptMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $donorName,
        public readonly string $amountFormatted,
        public readonly string $receiptNumber,
        public readonly string $campaignTitle,
        public readonly string $signedUrl,
    ) {}

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name', 'Temple Trust');

        return new Envelope(
            subject: sprintf(
                'Your donation receipt %s — %s',
                $this->receiptNumber,
                $appName,
            ),
        );
    }

    public function content(): Content
    {
        $appName = (string) config('app.name', 'Temple Trust');

        return new Content(
            text: 'emails.receipt-plain',
            with: [
                'donorName' => $this->donorName,
                'amountFormatted' => $this->amountFormatted,
                'receiptNumber' => $this->receiptNumber,
                'campaignTitle' => $this->campaignTitle,
                'signedUrl' => $this->signedUrl,
                'appName' => $appName,
            ],
        );
    }
}
