<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

interface PaymentDocumentRepositoryContract
{
    public function ensureReceiptDocument(string $fileAssetId): void;
}
