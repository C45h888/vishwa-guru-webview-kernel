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
 * Looks up by ReceiptRepositoryContract::findByReceiptNumber and
 * returns a public-shape summary. 404 on miss.
 *
 * The receiptNumber regex is enforced at the route level
 * (routes/receipts.php): TR-\d{4}-[A-Z0-9]{4,32}.
 */
final class ReceiptController
{
    public function __invoke(ReceiptRepositoryContract $receipts, string $receiptNumber): Response
    {
        $receipt = $receipts->findByReceiptNumber($receiptNumber);
        if ($receipt === null) {
            throw new NotFoundHttpException("Receipt [{$receiptNumber}] not found");
        }

        return Inertia::render('payments/Receipt', [
            'receipt' => $receipt->toReadProjection(),
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
