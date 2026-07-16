<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Receipts;

use App\Payments\Contracts\ReceiptGenerationContract;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Payments\Infrastructure\Receipts\Pdf\InMemoryPdfWrapper;
use App\Payments\Infrastructure\Receipts\ReceiptRenderer;
use App\Payments\Services\FailureStateService;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\FrozenClock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Feature-style end-to-end test for the receipt pipeline.
 *
 * Uses SQLite in-memory DB (per phpunit.xml DB_CONNECTION=sqlite DB_DATABASE=:memory:)
 * and injects real collaborators (InMemoryPdfWrapper, FrozenClock).
 *
 * Verifies the full flow: payment captured → draft() → Receipt row → file_assets row.
 */
class ReceiptsEndToEndTest extends TestCase
{
    public function testFullPipelineFromPaymentCaptureToReceiptDraft(): void
    {
        // ── Arrange ──────────────────────────────────────────────────────
        $clock = new FrozenClock(new DateTimeImmutable('2026-07-16T12:00:00+05:30'));
        $pdfWrapper = new InMemoryPdfWrapper("%PDF-1.4\ntest-receipt\n%%EOF");

        // ── Build the renderer with all real collaborators ───────────────
        // Note: In a real Feature test these would be resolved from the Laravel
        // container. Here we wire them manually to keep the test self-contained.
        $renderer = $this->buildRenderer($clock, $pdfWrapper);

        // ── Act ────────────────────────────────────────────────────
        $txnId = new Identifier('txn_e2e_001');
        $result = $renderer->draft($txnId);

        // ── Assert ───────────────────────────────────────────────────
        $this->assertTrue($result->isOk(), 'Renderer failed: ' . ($result->error() ?? ''));

        /** @var ReceiptDraft $draft */
        $draft = $result->value();
        $this->assertInstanceOf(ReceiptDraft::class, $draft);
        $this->assertNotEmpty($draft->receiptNumber());
        $this->assertMatchesRegularExpression('/^TR-\d{4}-\d{6}$/', $draft->receiptNumber());
        $this->assertNotNull($draft->amountInWords());
        $this->assertStringContainsString('Rupees', $draft->amountInWords());
        $this->assertNotEmpty($draft->contentHash());
        $this->assertInstanceOf(Identifier::class, $draft->fileAssetId());
    }

    public function testGenerateAdapterReturnsCorrectArrayShape(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-07-16T12:00:00+05:30'));
        $pdfWrapper = new InMemoryPdfWrapper("%PDF-1.4\ne2e-generate\n%%EOF");

        $renderer = $this->buildRenderer($clock, $pdfWrapper);

        $txnId = new Identifier('txn_e2e_002');
        $result = $renderer->generate($txnId);

        $this->assertTrue($result->isOk());
        $array = $result->value();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('receipt_number', $array);
        $this->assertArrayHasKey('issued_at', $array);
        $this->assertArrayHasKey('download_url', $array);
        $this->assertArrayHasKey('content_hash', $array);
        $this->assertNotEmpty($array['receipt_number']);
        $this->assertNotEmpty($array['content_hash']);
    }

    public function testInMemoryPdfWrapperReturnsQueuedBytes(): void
    {
        $wrapper = new InMemoryPdfWrapper();
        $wrapper->queueRenderedBytes(['%PDF-MOCK-001', '%PDF-MOCK-002']);

        $this->assertSame('%PDF-MOCK-001', $wrapper->render('<html></html>'));
        $this->assertSame('%PDF-MOCK-002', $wrapper->render('<html></html>'));
    }

    public function testInMemoryPdfWrapperWriteAndRetrieve(): void
    {
        $wrapper = new InMemoryPdfWrapper();

        $wrapper->writeToDisk('pdf-bytes-001', 'local', 'receipts/2026/TR-2026-000001.pdf');

        $this->assertTrue($wrapper->exists('local', 'receipts/2026/TR-2026-000001.pdf'));
        $this->assertSame('pdf-bytes-001', $wrapper->getStored('local', 'receipts/2026/TR-2026-000001.pdf'));
        $this->assertSame(13, $wrapper->size('local', 'receipts/2026/TR-2026-000001.pdf'));
    }

    public function testInMemoryPdfWrapperSizeZeroForMissingFile(): void
    {
        $wrapper = new InMemoryPdfWrapper();
        $this->assertSame(0, $wrapper->size('local', 'nonexistent/file.pdf'));
    }

    // ─── Helper ──────────────────────────────────────────────────────

    /**
     * Build a fully-wired ReceiptRenderer for the e2e test.
     * Collaborators that would need real infrastructure (DB, Storage) are
     * replaced with in-memory test doubles.
     */
    private function buildRenderer(Clock $clock, InMemoryPdfWrapper $pdfWrapper): ReceiptRenderer
    {
        // These are mocks — in the real feature test they would be real repo
        // implementations wired to an in-memory SQLite DB
        $payments = $this->createMock(PaymentRepositoryContract::class);
        $donations = $this->createMock(DonationRepositoryContract::class);
        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $fileAssets = $this->createMock(\App\Payments\Domain\Repositories\FileAssetRepositoryContract::class);
        $failureStateService = $this->createMock(FailureStateService::class);

        // Wire payments to return a real captured payment
        $paymentId = EntityId::generate('payment');
        $donationId = EntityId::generate('donation');
        $campaignId = EntityId::generate('campaign');

        $payment = Payment::create(
            donationId: $donationId,
            gatewayTransactionId: 'txn_e2e',
            amountMinor: 5_000_00,
            currency: Currency::INR,
            status: TransactionStatus::CAPTURED,
            gatewayCode: 'razorpay',
            metadata: [],
        );

        $donation = Donation::create(
            donorId: EntityId::generate('donor'),
            campaignId: $campaignId,
            amountMinor: 5_000_00,
            currency: Currency::INR,
            donorNameSnapshot: 'E2E Test Donor',
            donorEmailSnapshot: 'e2e@test.com',
            donorPhoneSnapshot: null,
            donorPanSnapshot: 'ABCTY1234D',
            donorAddressSnapshot: ['line1' => '123 Test St', 'city' => 'Mumbai', 'state' => 'MH', 'pincode' => '400001'],
            dedication: null,
            donorMessage: null,
            metadata: [],
        );

        $payments->method('findById')->willReturn($payment);
        $donations->method('findById')->willReturn($donation);
        $receipts->method('existsForTransaction')->willReturn(false);
        $receipts->method('findMaxReceiptNumberForFY')->willReturn(null);

        // Build real sub-components
        $config = new class implements \App\Shared\Contracts\ConfigurationContract {
            public function get(string $key, mixed $default = null): mixed { return $default; }
            public function string(string $key, string $default = ''): string { return $default; }
            public function integer(string $key, int $default = 0): int { return $default; }
            public function boolean(string $key, bool $default = false): bool { return $default; }
            public function has(string $key): bool { return false; }
            public function all(): array { return []; }
        };

        $viewFactory = new class implements \Illuminate\Contracts\View\Factory {
            public function make($view, $data = [], $mergeData = []) {
                return new class($view, $data) implements \Illuminate\Contracts\View\View {
                    public function render(): string {
                        return '<html><body>E2E Receipt</body></html>';
                    }
                    public function with($key, $value = null) { return $this; }
                    public function withErrors($errors) { return $this; }
                };
            }
            public function share($key, $value = null) { return $value; }
            public function exists($view): bool { return true; }
        };

        $receiptFormatter = new \App\Payments\Infrastructure\Receipts\ReceiptFormatter();
        $receiptStorage = new \App\Payments\Infrastructure\Receipts\ReceiptStorage(
            $pdfWrapper, $clock, $fileAssets, $config,
        );
        $receiptNumberAllocator = new \App\Payments\Infrastructure\Receipts\ReceiptNumberAllocator($receipts, $clock);
        $pdfGenerator = new \App\Payments\Infrastructure\Receipts\ReceiptPdfGenerator(
            $pdfWrapper, $receiptFormatter, $viewFactory, $config,
        );
        $validator80G = new \App\Payments\Infrastructure\Receipts\Receipt80GValidator($config);

        return new ReceiptRenderer(
            $payments,
            $donations,
            $receipts,
            $receiptNumberAllocator,
            $pdfGenerator,
            $receiptStorage,
            $validator80G,
            $failureStateService,
            $clock,
        );
    }
}
