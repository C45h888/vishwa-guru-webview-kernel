<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Receipts;

use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Domain\ValueObjects\FileAssetRecord;
use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Payments\Infrastructure\Receipts\Pdf\InMemoryPdfWrapper;
use App\Payments\Infrastructure\Receipts\ReceiptNumberAllocator;
use App\Payments\Infrastructure\Receipts\ReceiptStorage;
use App\Payments\Receipts\ReceiptSubstrate;
use App\Payments\Services\FailureStateService;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\FrozenClock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use PHPUnit\Framework\TestCase;

/**
 * ReceiptSubstrateTest — the canonical generation logic's test plane.
 *
 * Ports the coverage of the absorbed classes (ReceiptRenderer,
 * ReceiptFormatter, Receipt80GValidator) onto the substrate surface, and
 * pins the substrate's core guarantee: the typed document carried by the
 * draft IS the snapshot the persisted receipt row is built from.
 */
final class ReceiptSubstrateTest extends TestCase
{
    private PaymentRepositoryContract $payments;
    private DonationRepositoryContract $donations;
    private ReceiptRepositoryContract $receipts;
    private CampaignRepositoryContract $campaigns;
    private ReceiptNumberAllocator $allocator;
    private ReceiptStorage $storage;
    private FailureStateService $failureStateService;
    private FrozenClock $clock;
    private InMemoryPdfWrapper $pdf;
    private ConfigurationContract $config;
    private ReceiptSubstrate $substrate;

    /** @var array<string, mixed> */
    private array $configValues = [];

    protected function setUp(): void
    {
        $this->payments = $this->createMock(PaymentRepositoryContract::class);
        $this->donations = $this->createMock(DonationRepositoryContract::class);
        $this->receipts = $this->createMock(ReceiptRepositoryContract::class);
        $this->campaigns = $this->createMock(CampaignRepositoryContract::class);
        $this->allocator = $this->createMock(ReceiptNumberAllocator::class);
        $this->storage = $this->createMock(ReceiptStorage::class);
        $this->failureStateService = $this->createMock(FailureStateService::class);
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-07-16T12:00:00+05:30'));
        $this->pdf = new InMemoryPdfWrapper('%PDF-1.4 substrate-test %%EOF');
        $this->configValues = [
            'receipts.enabled' => true,
            'receipts.80g.trust_registered' => true,
            'receipts.80g.trust_registration_number' => 'AAATS1234R',
            'receipts.80g.trust_pan' => 'AAACT1234D',
            'receipts.80g.certificate_threshold_minor' => 500_00,
            'receipts.branding.trust_name' => 'Temple Trust',
            'receipts.branding.trust_address' => '12 Temple St',
            'receipts.branding.trust_email' => 'trust@example.com',
            'receipts.branding.trust_phone' => '+91-98765-43210',
            'receipts.workers' => [
                'data' => ['timeout_ms' => 1000, 'max_attempts' => 2],
                'types' => ['timeout_ms' => 1000, 'max_attempts' => 2],
                'design' => ['timeout_ms' => 1000, 'max_attempts' => 2],
                'backoff_ms' => [1],
            ],
        ];

        $this->config = new class($this->configValues) implements ConfigurationContract {
            /** @param array<string, mixed> $values */
            public function __construct(private array $values) {}
            public function get(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
            public function string(string $key, string $default = ''): string
            {
                return (string) ($this->values[$key] ?? $default);
            }
            public function integer(string $key, int $default = 0): int
            {
                return (int) ($this->values[$key] ?? $default);
            }
            public function boolean(string $key, bool $default = false): bool
            {
                return (bool) ($this->values[$key] ?? $default);
            }
            public function has(string $key): bool
            {
                return array_key_exists($key, $this->values);
            }
            public function all(): array
            {
                return $this->values;
            }
        };

        $viewFactory = new class implements \Illuminate\Contracts\View\Factory {
            public function make($view, $data = [], $mergeData = [])
            {
                return new class($view, $data) implements \Illuminate\Contracts\View\View {
                    /** @param array<string, mixed> $data */
                    public function __construct(private string $view, private array $data) {}
                    public function render(): string
                    {
                        return '<html><body>'.htmlspecialchars((string) json_encode($this->data)).'</body></html>';
                    }
                    public function with($key, $value = null) { return $this; }
                    public function withErrors($errors) { return $this; }
                    public function name() { return $this->view; }
                    public function getData() { return $this->data; }
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

        $this->substrate = new ReceiptSubstrate(
            payments: $this->payments,
            donations: $this->donations,
            campaigns: $this->campaigns,
            receipts: $this->receipts,
            allocator: $this->allocator,
            storage: $this->storage,
            failureStateService: $this->failureStateService,
            config: $this->config,
            clock: $this->clock,
            views: $viewFactory,
            pdf: $this->pdf,
        );
    }

    // ─── Pipeline (ported from ReceiptRendererTest) ─────────────────────

    public function testDraftSucceedsForSuccessfulPayment(): void
    {
        $paymentId = EntityId::generate('payment');
        $this->wireHappyPath($paymentId);
        $this->allocator->method('next')->willReturn('TR-2026-000042-A7c3ZpQ9');

        $result = $this->substrate->draft(new Identifier($paymentId->ulid()));

        self::assertTrue($result->isOk(), 'draft failed: '.(string) $result->error());
        $draft = $result->value();
        self::assertInstanceOf(ReceiptDraft::class, $draft);
        self::assertMatchesRegularExpression('/^TR-\d{4}-\d{6}-[A-Za-z0-9_-]{8}$/', $draft->receiptNumber());
    }

    public function testDraftReturnsFailureForNotFoundPayment(): void
    {
        $this->payments->method('findById')->willReturn(null);

        $result = $this->substrate->draft(new Identifier(EntityId::generate('payment')->ulid()));

        self::assertTrue($result->isFailure());
    }

    public function testDraftReturnsFailureForNonSuccessfulPayment(): void
    {
        $payment = Payment::initialize(
            donationId: EntityId::generate('donation'),
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 5_000_00,
            currency: Currency::INR,
            idempotencyKey: 'k',
            metadata: [],
        );
        $this->payments->method('findById')->willReturn($payment);

        $result = $this->substrate->draft(new Identifier(EntityId::generate('payment')->ulid()));

        self::assertTrue($result->isFailure());
        self::assertStringContainsString('cannot issue receipt', (string) $result->error());
    }

    public function testDraftIsIdempotentWhenReceiptExists(): void
    {
        $paymentId = EntityId::generate('payment');
        $this->payments->method('findById')->willReturn($this->makeCapturedPayment($paymentId));
        $existing = $this->makeReceipt($paymentId);
        $this->receipts->method('findByTransactionId')->willReturn($existing);

        $result = $this->substrate->draft(new Identifier($paymentId->ulid()));

        self::assertTrue($result->isOk());
        self::assertSame($existing->receiptNumber(), $result->value()->receiptNumber());
    }

    public function testGenerateAdapterMapsDraftToArrayShape(): void
    {
        $paymentId = EntityId::generate('payment');
        $this->wireHappyPath($paymentId);
        $this->allocator->method('next')->willReturn('TR-2026-000099-Zz9_-aBc');

        $result = $this->substrate->generate(new Identifier($paymentId->ulid()));

        self::assertTrue($result->isOk());
        $array = $result->value();
        self::assertArrayHasKey('receipt_number', $array);
        self::assertArrayHasKey('issued_at', $array);
        self::assertArrayHasKey('download_url', $array);
        self::assertArrayHasKey('content_hash', $array);
    }

    /**
     * THE canonicality guarantee: the typed document carried by the draft
     * holds the same facts the persisted row is built from (80G decision,
     * donor + campaign snapshots, amounts).
     */
    public function testDraftDocumentIsThePersistedSnapshot(): void
    {
        $paymentId = EntityId::generate('payment');
        $this->wireHappyPath($paymentId);
        $this->allocator->method('next')->willReturn('TR-2026-000042-A7c3ZpQ9');

        $result = $this->substrate->draft(new Identifier($paymentId->ulid()));

        self::assertTrue($result->isOk());
        /** @var ReceiptDraft $draft */
        $draft = $result->value();
        $doc = $draft->document();

        self::assertInstanceOf(ReceiptDocument::class, $doc);
        self::assertSame($draft->receiptNumber(), $doc->receiptNumber);
        self::assertSame('Test Donor', $doc->donorName);
        self::assertSame('Test Campaign', $doc->campaignTitle);
        self::assertSame(5_000_00, $doc->amountMinor);
        self::assertSame('₹5,000.00', $doc->amountDisplay);
        self::assertSame('FY 2026-27', $doc->fyLabel);
        self::assertTrue($doc->tax80gEligible, '80G decision must flow into the document');
        self::assertNotNull($doc->tax80gCertificateNumber);
        self::assertMatchesRegularExpression('/^80G\/2026\/AAATS1234R\/[A-F0-9]{6}$/', $doc->tax80gCertificateNumber);
        self::assertSame('AAATS1234R', $doc->tax80gRegistrationNumber);
    }

    // ─── verify80G (ported from Receipt80GValidatorTest) ────────────────

    public function testVerify80gRejectsNonInr(): void
    {
        $r = $this->substrate->verify80G('ABCTY1234D', 5_000_00, Currency::USD, new \DateTimeImmutable());

        self::assertFalse($r['eligible']);
        self::assertStringContainsString('INR', (string) $r['reason']);
    }

    public function testVerify80gRejectsWhenTrustUnregistered(): void
    {
        $this->setConfig('receipts.80g.trust_registered', false);

        $r = $this->substrate->verify80G('ABCTY1234D', 5_000_00, Currency::INR, new \DateTimeImmutable());

        self::assertFalse($r['eligible']);
    }

    public function testVerify80gRequiresPanAboveThreshold(): void
    {
        $r = $this->substrate->verify80G(null, 1_000_00, Currency::INR, new \DateTimeImmutable());

        self::assertFalse($r['eligible']);
        self::assertTrue($r['certificate_required']);
        self::assertNotNull($r['note']);
    }

    public function testVerify80gRejectsInvalidPan(): void
    {
        $r = $this->substrate->verify80G('NOTAPAN', 5_000_00, Currency::INR, new \DateTimeImmutable());

        self::assertFalse($r['eligible']);
        self::assertStringContainsString('PAN', (string) $r['reason']);
    }

    public function testVerify80gEligibleIssuesCertificateAboveThreshold(): void
    {
        $r = $this->substrate->verify80G('ABCTY1234D', 5_000_00, Currency::INR, new \DateTimeImmutable('2026-07-16'));

        self::assertTrue($r['eligible']);
        self::assertTrue($r['certificate_required']);
        self::assertMatchesRegularExpression('/^80G\/2026\/AAATS1234R\/[A-F0-9]{6}$/', (string) $r['certificate_number']);
        self::assertSame('AAATS1234R', $r['trust_registration_number']);
    }

    public function testVerify80gEligibleWithoutCertificateBelowThreshold(): void
    {
        $r = $this->substrate->verify80G('ABCTY1234D', 100_00, Currency::INR, new \DateTimeImmutable('2026-07-16'));

        self::assertTrue($r['eligible']);
        self::assertFalse($r['certificate_required']);
        self::assertNull($r['certificate_number']);
    }

    // ─── Formatting (ported from ReceiptFormatterTest) ──────────────────

    public function testFormatMoneyUsesIndianGroupingForInr(): void
    {
        self::assertSame('₹5,000.00', $this->substrate->formatMoney(5_000_00, Currency::INR));
        self::assertSame('₹1,00,000.00', $this->substrate->formatMoney(1_00_000_00, Currency::INR));
        self::assertSame('₹0.50', $this->substrate->formatMoney(50, Currency::INR));
    }

    public function testFormatDateUsesCanonicalDisplayDialect(): void
    {
        $date = new \DateTimeImmutable('2026-07-16T12:00:00+05:30');

        self::assertSame('16 July 2026', $this->substrate->formatDate($date));
    }

    public function testFormatFyUsesIndianFiscalYear(): void
    {
        self::assertSame('FY 2026-27', $this->substrate->formatFY(new \DateTimeImmutable('2026-07-16')));
        self::assertSame('FY 2026-27', $this->substrate->formatFY(new \DateTimeImmutable('2027-02-01')));
        self::assertSame('FY 2027-28', $this->substrate->formatFY(new \DateTimeImmutable('2027-04-01')));
    }

    public function testFormatAddressBlockWithFullAddress(): void
    {
        $block = $this->substrate->formatAddressBlock([
            'line1' => '123 Main Street',
            'line2' => 'Apt 4B',
            'city' => 'Mumbai',
            'state' => 'MH',
            'pincode' => '400001',
        ]);

        self::assertStringContainsString('123 Main Street', $block);
        self::assertStringContainsString('Mumbai, MH 400001', $block);
    }

    // ─── Internals ─────────────────────────────────────────────────────

    private function setConfig(string $key, mixed $value): void
    {
        $values = $this->config->all();
        $values[$key] = $value;
        $ref = new \ReflectionProperty($this->config, 'values');
        $ref->setValue($this->config, $values);
    }

    private function wireHappyPath(EntityId $paymentId): void
    {
        $this->payments->method('findById')->willReturn($this->makeCapturedPayment($paymentId));
        $this->donations->method('findById')->willReturn($this->makeDonation());
        $this->campaigns->method('findById')->willReturn($this->makeCampaign());
        $this->receipts->method('findByTransactionId')->willReturn(null);
        $this->storage->method('persist')->willReturn(Result::success($this->makeFileAssetRecord()));
    }

    private function makeCapturedPayment(EntityId $id): Payment
    {
        $payment = Payment::initialize(
            donationId: EntityId::generate('donation'),
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 5_000_00,
            currency: Currency::INR,
            idempotencyKey: $id->ulid(),
            metadata: [],
        );
        $machine = new PaymentStateMachine();
        $payment = $payment->transitionTo($machine, TransactionStatus::PENDING);
        $payment = $payment->transitionTo($machine, TransactionStatus::AUTHORIZED);
        $payment = $payment->transitionTo($machine, TransactionStatus::CAPTURED, [
            'amount_minor' => 5_000_00,
        ]);

        return $payment;
    }

    private function makeDonation(): Donation
    {
        return Donation::draft(
            campaignId: EntityId::generate('campaign'),
            donor: DonorIdentity::identified(
                name: 'Test Donor',
                email: 'donor@test.com',
                phone: null,
                pan: 'ABCTY1234D',
                address: ['line1' => '12 Test Lane', 'city' => 'Mumbai', 'state' => 'MH', 'pincode' => '400001'],
            ),
            amountMinor: 5_000_00,
            currency: Currency::INR,
            id: EntityId::generate('donation'),
        );
    }

    private function makeReceipt(EntityId $paymentId): Receipt
    {
        return Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: $paymentId,
            campaignId: EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000001-AAAA1111',
            campaignTitleSnapshot: 'Test Campaign',
            donorName: 'Test Donor',
            amountMinor: 5_000_00,
            currency: Currency::INR,
            contentHash: str_repeat('a', 64),
            receiptFileId: EntityId::fromString('file_asset_'.EntityId::generate('file_asset')->ulid()),
        );
    }

    private function makeCampaign(): \App\Campaigns\Domain\DTOs\CampaignDetailDTO
    {
        $now = new \DateTimeImmutable('2026-07-16T12:00:00+05:30');

        return new \App\Campaigns\Domain\DTOs\CampaignDetailDTO(
            id: 'campaign_'.EntityId::generate('campaign')->ulid(),
            slug: 'test-campaign',
            title: 'Test Campaign',
            shortDescription: null,
            description: null,
            category: 'general',
            currencyCode: 'INR',
            targetAmountMinor: null,
            isFeatured: false,
            isActive: true,
            state: 'active',
            displayOrder: 0,
            startsAt: null,
            endsAt: null,
            coverImageFileId: null,
            metadata: [],
            createdAt: $now,
        );
    }

    private function makeFileAssetRecord(): FileAssetRecord
    {
        $now = new \DateTimeImmutable('2026-07-16T12:00:00+05:30');

        return new FileAssetRecord(
            id: 'file_asset_'.EntityId::generate('file_asset')->ulid(),
            ownerType: 'receipt_pdf',
            ownerId: EntityId::generate('receipt')->ulid(),
            originalFilename: 'TR-2026-000042-A7c3ZpQ9.pdf',
            storageDisk: 'local',
            storagePath: 'receipts/2026/TR-2026-000042-A7c3ZpQ9.pdf',
            mimeType: 'application/pdf',
            fileSizeBytes: 1024,
            fileHashSha256: str_repeat('b', 64),
            purpose: 'receipt_pdf',
            isPublic: false,
            isArchived: false,
            archivedAt: null,
            metadata: [],
            uploadedAt: $now,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
