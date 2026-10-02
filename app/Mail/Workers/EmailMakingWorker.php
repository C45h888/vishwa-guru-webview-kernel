<?php

declare(strict_types=1);

namespace App\Mail\Workers;

use App\Mail\MailSubstrate;
use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Shared\Support\Result;

/**
 * EmailMakingWorker — THE EMAIL WRITER.
 *
 * Takes the typed document + the designed receipt PDF ("the receipt from
 * the webview") and writes the email: subject/body via the mother file's
 * canonical composeReceiptEmail(), and the PDF attached as the official
 * instrument (base64, named after the receipt number).
 *
 * It imports ALL writing logic from the MailSubstrate — this worker
 * composes, it never decides. Every receipt email in the system is
 * assembled here, from the same typed snapshot the PDF design rendered.
 */
final class EmailMakingWorker
{
    public function __construct(
        private readonly MailSubstrate $substrate,
    ) {
    }

    /**
     * @return Result<array{
     *     subject: string,
     *     text: string,
     *     html: string,
     *     attachments: array<int, array<string, mixed>>,
     * }>
     */
    public function compose(ReceiptDocument $document, string $signedUrl, ?string $pdfBytes): Result
    {
        if ($signedUrl === '') {
            return Result::failure('mail.composition: signed url missing');
        }

        $message = $this->substrate->composeReceiptEmail($document, $signedUrl);

        $attachments = [];
        if ($pdfBytes !== null && $pdfBytes !== '') {
            $attachments[] = [
                'filename' => $document->receiptNumber.'.pdf',
                'content' => base64_encode($pdfBytes),
                'content_type' => 'application/pdf',
                'encoding' => 'base64',
            ];
        }

        return Result::success([
            'subject' => $message['subject'],
            'text' => $message['text'],
            'html' => $message['html'],
            'attachments' => $attachments,
        ]);
    }
}
