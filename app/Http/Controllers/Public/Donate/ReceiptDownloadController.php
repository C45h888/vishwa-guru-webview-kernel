<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Donate;

use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Infrastructure\Receipts\ReceiptPdfGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Receipt PDF download (GET /receipts/{number}/download).
 *
 * Doctrine (constitutional):
 *   - Pure transport. The controller does NOT construct or mutate any
 *     aggregate; PDF rendering goes through ReceiptPdfGenerator.
 *   - ReceiptPdfGenerator::render accepts (Receipt, Payment, Donation)
 *     and returns Result<string> — we treat failure as 404 (PDF unavailable)
 *     rather than 500, because the receipt entity exists in DB but its
 *     file render failed (cache miss, transient error).
 *   - The receipt number is the access token (cryptographic randomness
 *     per ReceiptNumberAllocator). When auth lands, add `auth` middleware
 *     here and gate to the donor's own receipts.
 *
 * Routes registered in routes/receipts.php with the same regex constraint
 * as /receipts/{number}: TR-\d{4}-[A-Z0-9]{4,32}.
 */
final class ReceiptDownloadController
{
    public function __invoke(
        ReceiptRepositoryContract $receipts,
        PaymentRepositoryContract $payments,
        DonationRepositoryContract $donations,
        ReceiptPdfGenerator $pdfGenerator,
        string $receiptNumber,
    ): Response {
        $receipt = $receipts->findByReceiptNumber($receiptNumber);
        if ($receipt === null) {
            throw new NotFoundHttpException(
                "Receipt [{$receiptNumber}] not found",
            );
        }

        $payment = $payments->findById($receipt->paymentId());
        $donation = $donations->findById($receipt->donationId());
        if ($payment === null || $donation === null) {
            throw new NotFoundHttpException(
                "Receipt [{$receiptNumber}] is missing its payment or donation record",
            );
        }

        $result = $pdfGenerator->render($receipt, $payment, $donation);
        if ($result->isFailure()) {
            throw new NotFoundHttpException(
                "Receipt [{$receiptNumber}] PDF could not be rendered",
            );
        }

        $bytes = $result->value();

        return new Response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$receiptNumber}.pdf\"",
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'ETag' => '"' . $receipt->contentHash() . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}