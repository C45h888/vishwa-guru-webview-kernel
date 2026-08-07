<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Donate;

use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Receipt detail page (GET /receipts/{receiptNumber}).
 *
 * Looks up by ReceiptRepositoryContract::findByAccessToken (the URL
 * `?t=<token>` query parameter) and returns a public-shape summary.
 * 404 on miss. The receipt number alone is never sufficient.
 *
 * The receiptNumber regex is enforced at the route level
 * (routes/receipts.php): TR-\d{4}-[A-Z0-9]{4,32}.
 */
final class ReceiptController
{
    public function __invoke(ReceiptRepositoryContract $receipts, string $receiptNumber, \Illuminate\Http\Request $request): Response
    {
        $token = (string) $request->query('t', '');
        if ($token === '') {
            throw new NotFoundHttpException("Receipt [{$receiptNumber}] requires an access token");
        }

        $receipt = $receipts->findByAccessToken($token);
        if ($receipt === null || $receipt->receiptNumber() !== $receiptNumber) {
            throw new NotFoundHttpException("Receipt [{$receiptNumber}] not found");
        }

        return Inertia::render('payments/Receipt', [
            'receipt' => $receipt->toReadProjection(),
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
