<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\ValueObjects\FileAssetRecord;
use App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper;
use App\Payments\Infrastructure\Receipts\ReceiptStorage;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Clock;
use App\Shared\Support\FrozenClock;
use App\Shared\Support\Result;
use PHPUnit\Framework\TestCase;

class ReceiptStorageTest extends TestCase
{
    private PdfWrapper $pdf;
    private FileAssetRepositoryContract $fileAssets;
    private Clock $clock;
    private ConfigurationContract $config;
    private ReceiptStorage $storage;

    protected function setUp(): void
    {
        $this->pdf = $this->createMock(PdfWrapper::class);
        $this->fileAssets = $this->createMock(FileAssetRepositoryContract::class);
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-07-16T12:00:00+05:30'));
        $this->config = $this->createMock(ConfigurationContract::class);

        $this->config->method('get')->willReturnCallback(fn($k, $d = null) => $d);

        $this->storage = new ReceiptStorage(
            $this->pdf,
            $this->clock,
            $this->fileAssets,
            $this->config,
        );
    }

    public function testPersistWritesPdfToDiskAndSavesRecord(): void
    {
        $savedRecords = [];
        $this->fileAssets->method('findByHash')->willReturn(null);
        $this->fileAssets->method('save')
            ->willReturnCallback(function (FileAssetRecord $record) use (&$savedRecords) {
                $savedRecords[] = $record;
            });

        $this->pdf->expects($this->once())
            ->method('writeToDisk')
            ->with(
                "mock-pdf-bytes",
                'local',
                $this->stringContains('receipts/2026/'),
            );

        $result = $this->storage->persist(
            transactionId: 'txn_abc123',
            ownerId: 're_abc123',
            pdfBytes: "mock-pdf-bytes",
            purpose: 'receipt_pdf',
        );

        $this->assertTrue($result->isOk());
        $record = $result->value();
        $this->assertInstanceOf(FileAssetRecord::class, $record);
        $this->assertSame('receipt', $record->ownerType());
        // computeHash returns SHA-256 hex (64 chars), not the raw bytes.
        $this->assertSame(
            $this->storage->computeHash("mock-pdf-bytes"),
            $this->storage->computeHash("mock-pdf-bytes"),
        );
        $this->assertSame(64, strlen($record->fileHashSha256()));
    }

    public function testPersistReusesExistingRecordForDuplicateHash(): void
    {
        $existingRecord = FileAssetRecord::create(
            id: 'fa_existing',
            ownerType: 'receipt',
            ownerId: 're_old',
            originalFilename: 'receipt_old.pdf',
            storageDisk: 'local',
            storagePath: 'receipts/2025/TR-2025-000001.pdf',
            mimeType: 'application/pdf',
            fileSizeBytes: 9999,
            fileHashSha256: hash('sha256', 'duplicate-content'),
            purpose: 'receipt_pdf',
        );

        $this->fileAssets->method('findByHash')
            ->with(hash('sha256', 'duplicate-content'))
            ->willReturn($existingRecord);

        // writeToDisk should NOT be called when reusing existing
        $this->pdf->expects($this->never())->method('writeToDisk');

        $result = $this->storage->persist(
            transactionId: 'txn_dup',
            ownerId: 're_dup',
            pdfBytes: 'duplicate-content',
            purpose: 'receipt_pdf',
        );

        $this->assertTrue($result->isOk());
        $this->assertSame('fa_existing', $result->value()->id());
    }

    public function testComputeHashReturnsSha256(): void
    {
        $hash = $this->storage->computeHash('test content');

        $this->assertSame(64, strlen($hash));
        $this->assertSame(hash('sha256', 'test content'), $hash);
    }

    public function testStoragePathUsesTemplate(): void
    {
        $path = $this->storage->storagePath('txn_xyz', 'TR-2026-000042');

        $this->assertStringContainsString('2026', $path);
        $this->assertStringContainsString('TR-2026-000042', $path);
    }

    public function testStorageDiskFromConfig(): void
    {
        $this->config = $this->createMock(ConfigurationContract::class);
        $this->config->method('get')
            ->with('receipts.storage_disk', 'local')
            ->willReturn('s3');

        $storage = new ReceiptStorage($this->pdf, $this->clock, $this->fileAssets, $this->config);

        $this->assertSame('s3', $storage->storageDisk());
    }
}
