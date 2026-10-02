<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Donate;

use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Receipts\ReceiptSubstrate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Receipt PDF download (GET /receipts/{number}/download).
 *
 * Doctrine (constitutional):
 *   - Pure transport. The controller does NOT construct or mutate any
 *     aggregate; PDF rendering goes through the receipt substrate
 *     (types → design worker pipeline).
 *   - Rendering failure is treated as 404 (PDF unavailable) rather than
 *     500, because the receipt entity exists in DB but its render failed
 *     (transient error).
 *   - Access requires `?t=<access_token>` (a 43-char URL-safe random
 *     minted at issue time). The receipt number alone is never
 *     sufficient — it is sequentially enumerable and would expose
 *     donor PAN, full address, email to anyone.
 *
 * Routes registered in routes/receipts.php with the same regex constraint
 * as /receipts/{number}: TR-\d{4}-[A-Z0-9]{4,32}.
 */
final class ReceiptDownloadController
{
    public function __invoke(
        ReceiptRepositoryContract $receipts,
        ReceiptSubstrate $substrate,
        string $receiptNumber,
        \Illuminate\Http\Request $request,
    ): Response {
        $token = (string) $request->query('t', '');
        if ($token === '') {
            throw new NotFoundHttpException(
                "Receipt [{$receiptNumber}] requires an access token",
            );
        }

        $receipt = $receipts->findByAccessToken($token);
        if ($receipt === null || $receipt->receiptNumber() !== $receiptNumber) {
            throw new NotFoundHttpException(
                "Receipt [{$receiptNumber}] not found",
            );
        }

        $result = $substrate->renderPdfFor($receipt);
        if ($result->isFailure()) {
            throw new NotFoundHttpException(
                "Receipt [{$receiptNumber}] PDF could not be rendered",
            );
        }

        /** @var string $bytes */
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
