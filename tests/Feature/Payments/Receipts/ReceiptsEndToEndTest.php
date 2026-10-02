<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Receipts;

use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Payments\Infrastructure\Receipts\Pdf\InMemoryPdfWrapper;
use App\Payments\Infrastructure\Receipts\ReceiptNumberAllocator;
use App\Payments\Infrastructure\Receipts\ReceiptStorage;
use App\Payments\Receipts\ReceiptSubstrate;
use App\Payments\Services\FailureStateService;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\FrozenClock;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Feature-style end-to-end test for the receipt pipeline.
 *
 * Uses SQLite in-memory DB (per phpunit.xml DB_CONNECTION=sqlite DB_DATABASE=:memory:)
 * and injects real collaborators (InMemoryPdfWrapper, FrozenClock).
 *
 * Verifies the full flow through the substrate: payment captured →
 * data → types → design workers → ReceiptDraft carrying the typed
 * document snapshot.
 */
class ReceiptsEndToEndTest extends TestCase
{
    public function testFullPipelineFromPaymentCaptureToReceiptDraft(): void
    {
        // ── Arrange ──────────────────────────────────────────────────────
        $clock = new FrozenClock(new DateTimeImmutable('2026-07-16T12:00:00+05:30'));
        $pdfWrapper = new InMemoryPdfWrapper("%PDF-1.4\ntest-receipt\n%%EOF");

        $substrate = $this->buildSubstrate($clock, $pdfWrapper);

        // ── Act ────────────────────────────────────────────────────
        $txnId = new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $result = $substrate->draft($txnId);

        // ── Assert ───────────────────────────────────────────────────
        $this->assertTrue($result->isOk(), 'Substrate failed: ' . ($result->error() ?? ''));

        /** @var ReceiptDraft $draft */
        $draft = $result->value();
        $this->assertInstanceOf(ReceiptDraft::class, $draft);
        $this->assertNotEmpty($draft->receiptNumber());
        $this->assertMatchesRegularExpression(
            '/^TR-\d{4}-\d{6}-[A-Za-z0-9_-]{8}$/',
            $draft->receiptNumber(),
        );
        $this->assertNotNull($draft->amountInWords());
        $this->assertStringContainsString('Rupees', $draft->amountInWords());
        $this->assertNotEmpty($draft->contentHash());
        $this->assertInstanceOf(Identifier::class, $draft->fileAssetId());

        // The typed snapshot travels with the draft — the single assembly
        // guarantee that keeps document and runtime state coherent.
        $this->assertNotNull($draft->document(), 'draft must carry the typed document snapshot');
        $this->assertSame($draft->receiptNumber(), $draft->document()->receiptNumber);
        $this->assertSame('E2E Test Donor', $draft->document()->donorName);
    }

    public function testGenerateAdapterReturnsCorrectArrayShape(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-07-16T12:00:00+05:30'));
        $pdfWrapper = new InMemoryPdfWrapper("%PDF-1.4\ne2e-generate\n%%EOF");

        $substrate = $this->buildSubstrate($clock, $pdfWrapper);

        $txnId = new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FB0');
        $result = $substrate->generate($txnId);

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
     * Build a fully-wired ReceiptSubstrate for the e2e test.
     * Collaborators that would need real infrastructure (DB, Storage) are
     * replaced with in-memory test doubles.
     */
    private function buildSubstrate(Clock $clock, InMemoryPdfWrapper $pdfWrapper): ReceiptSubstrate
    {
        $payments = $this->createMock(PaymentRepositoryContract::class);
        $donations = $this->createMock(DonationRepositoryContract::class);
        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $campaigns = $this->createMock(CampaignRepositoryContract::class);
        $fileAssets = $this->createMock(FileAssetRepositoryContract::class);
        $failureStateService = $this->createMock(FailureStateService::class);

        // Wire payments to return a real captured payment (via the FSM —
        // Payment::withChanges() refuses to set status directly).
        $paymentId = EntityId::generate('payment');
        $donationId = EntityId::generate('donation');
        $campaignId = EntityId::generate('campaign');

        $payment = Payment::initialize(
            donationId: $donationId,
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 5_000_00,
            currency: Currency::INR,
            idempotencyKey: 'txn_e2e',
            metadata: [],
        );
        $machine = new PaymentStateMachine();
        $payment = $payment->transitionTo($machine, TransactionStatus::PENDING);
        $payment = $payment->transitionTo($machine, TransactionStatus::AUTHORIZED);
        $payment = $payment->transitionTo($machine, TransactionStatus::CAPTURED, [
            'amount_minor' => 5_000_00,
        ]);

        $donation = Donation::draft(
            campaignId: $campaignId,
            donor: \App\Payments\Domain\ValueObjects\DonorIdentity::identified(
                name: 'E2E Test Donor',
                email: 'e2e@test.com',
                phone: null,
                pan: 'ABCTY1234D',
                address: ['line1' => '123 Test St', 'city' => 'Mumbai', 'state' => 'MH', 'pincode' => '400001'],
            ),
            amountMinor: 5_000_00,
            currency: Currency::INR,
            id: $donationId,
        );

        $payments->method('findById')->willReturn($payment);
        $donations->method('findById')->willReturn($donation);
        $receipts->method('existsForTransaction')->willReturn(false);
        $receipts->method('findByTransactionId')->willReturn(null);
        $receipts->method('findMaxReceiptNumberForFY')->willReturn(null);
        $campaigns->method('findById')->willReturn(null);

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
                    public function name() { return 'e2e-receipt'; }
                    public function getData() { return []; }
                };
            }
            public function share($key, $value = null) { return $value; }
            public function exists($view): bool { return true; }
            public function file($path, $data = [], $mergeData = []) { return $this->make($path, $data); }
            public function composer($views, $callback) { return $this; }
            public function creator($views, $callback) { return $this; }
            public function addNamespace($namespace, $hints) { return $this; }
            public function replaceNamespace($namespace, $hints) { return $this; }
        };

        $receiptStorage = new ReceiptStorage(
            $pdfWrapper, $clock, $fileAssets, $config,
        );
        $receiptNumberAllocator = new ReceiptNumberAllocator($receipts, $clock);

        return new ReceiptSubstrate(
            payments: $payments,
            donations: $donations,
            campaigns: $campaigns,
            receipts: $receipts,
            allocator: $receiptNumberAllocator,
            storage: $receiptStorage,
            failureStateService: $failureStateService,
            config: $config,
            clock: $clock,
            views: $viewFactory,
            pdf: $pdfWrapper,
        );
    }
}
