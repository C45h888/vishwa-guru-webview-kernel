<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Donate;

use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Receipts\ReceiptSubstrate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Receipt detail page (GET /receipts/{receiptNumber}).
 *
 * Looks up by ReceiptRepositoryContract::findByAccessToken (the URL
 * `?t=<token>` query parameter) and returns the canonical public-shape
 * projection from the typed ReceiptDocument (built by the receipt
 * substrate). 404 on miss. The receipt number alone is never sufficient.
 *
 * The receiptNumber regex is enforced at the route level
 * (routes/receipts.php): TR-\d{4}-[A-Z0-9]{4,32}.
 */
final class ReceiptController
{
    public function __invoke(
        ReceiptRepositoryContract $receipts,
        ReceiptSubstrate $substrate,
        string $receiptNumber,
        \Illuminate\Http\Request $request,
    ): Response {
        $token = (string) $request->query('t', '');
        if ($token === '') {
            throw new NotFoundHttpException("Receipt [{$receiptNumber}] requires an access token");
        }

        $receipt = $receipts->findByAccessToken($token);
        if ($receipt === null || $receipt->receiptNumber() !== $receiptNumber) {
            throw new NotFoundHttpException("Receipt [{$receiptNumber}] not found");
        }

        // Canonical typed document — the same snapshot the design renders,
        // projected to the web wire shape. The access token is NOT echoed
        // back in the payload; the page builds gated links from `?t=` in
        // its own URL.
        $document = $substrate->documentFor($receipt);

        return Inertia::render('payments/Receipt', [
            'receipt' => $document->toReadProjection(),
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
