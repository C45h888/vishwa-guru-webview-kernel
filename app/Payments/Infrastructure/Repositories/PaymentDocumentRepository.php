<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Repositories\PaymentDocumentRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use RuntimeException;

final class PaymentDocumentRepository implements PaymentDocumentRepositoryContract
{
    public function __construct(private readonly PersistenceAdapterContract $adapter) {}

    public function ensureReceiptDocument(string $fileAssetId): void
    {
        $result = $this->adapter->execute(
            'INSERT INTO payment_document_assets (
                id, file_asset_id, document_type, state, access_classification,
                immutable_at, created_at, updated_at
             ) VALUES (
                :id, :file_asset_id, :document_type, :state, :access_classification,
                CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             ) ON CONFLICT (id) DO NOTHING',
            [
                'id' => $fileAssetId,
                'file_asset_id' => $fileAssetId,
                'document_type' => 'receipt',
                'state' => 'generated',
                'access_classification' => 'donor',
            ],
        );
        if ($result->isFailure()) {
            throw new RuntimeException('Payment document persistence failed: '.$result->error());
        }
    }
}
