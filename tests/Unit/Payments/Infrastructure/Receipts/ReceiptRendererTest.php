<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper;
use App\Payments\Infrastructure\Receipts\Receipt80GValidator;
use App\Payments\Infrastructure\Receipts\ReceiptNumberAllocator;
use App\Payments\Infrastructure\Receipts\ReceiptPdfGenerator;
use App\Payments\Infrastructure\Receipts\ReceiptRenderer;
use App\Payments\Infrastructure\Receipts\ReceiptStorage;
use App\Payments\Services\FailureStateService;
use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\FrozenClock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use PHPUnit\Framework\TestCase;

class ReceiptRendererTest extends TestCase
{
    private PaymentRepositoryContract $payments;
    private DonationRepositoryContract $donations;
    private ReceiptRepositoryContract $receipts;
    private ReceiptNumberAllocator $allocator;
    private ReceiptPdfGenerator $pdfGenerator;
    private ReceiptStorage $storage;
    private Receipt80GValidator $validator80G;
    private FailureStateService $failureStateService;
    private Clock $clock;
    private CampaignRepositoryContract $campaigns;
    private ReceiptRenderer $renderer;

    protected function setUp(): void
    {
        $this->payments = $this->createMock(PaymentRepositoryContract::class);
        $this->donations = $this->createMock(DonationRepositoryContract::class);
        $this->receipts = $this->createMock(ReceiptRepositoryContract::class);
        $this->allocator = $this->createMock(ReceiptNumberAllocator::class);
        $this->pdfGenerator = $this->createMock(ReceiptPdfGenerator::class);
        $this->storage = $this->createMock(ReceiptStorage::class);
        $this->validator80G = $this->createMock(Receipt80GValidator::class);
        $this->failureStateService = $this->createMock(FailureStateService::class);
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-07-16T12:00:00+05:30'));
        $this->campaigns = $this->createMock(CampaignRepositoryContract::class);

        $this->renderer = new ReceiptRenderer(
            $this->payments,
            $this->donations,
            $this->receipts,
            $this->allocator,
            $this->pdfGenerator,
            $this->storage,
            $this->validator80G,
            $this->failureStateService,
            $this->campaigns,
            $this->clock,
        );
    }

    public function testDraftSucceedsForSuccessfulPayment(): void
    {
        $txnId = new Identifier(\App\Shared\Support\UlidGenerator::generate());
        $paymentId = EntityId::generate('payment');
        $donationId = EntityId::generate('donation');
        $payment = $this->makePayment($paymentId, TransactionStatus::CAPTURED);
        $donation = $this->makeDonation($donationId);
        $fileAsset = $this->makeFileAssetRecord();

        $this->payments->method('findById')->willReturn($payment);
        $this->receipts->method('existsForTransaction')->willReturn(false);
        $this->donations->method('findById')->willReturn($donation);
        $this->allocator->method('next')->willReturn('TR-2026-000001');
        $this->validator80G->method('isEligible')->willReturn(Result::success([
            'eligible' => false,
            'reason' => null,
            'certificate_required' => false,
            'certificate_number' => null,
        ]));
        $this->pdfGenerator->method('render')->willReturn(Result::success('%PDF-1.4 mock'));
        $this->storage->method('persist')->willReturn(Result::success($fileAsset));

        $result = $this->renderer->draft($txnId);

        $this->assertTrue($result->isOk(), 'Expected success but got: ' . ($result->error() ?? ''));

        /** @var ReceiptDraft $draft */
        $draft = $result->value();
        $this->assertInstanceOf(ReceiptDraft::class, $draft);
        $this->assertSame('TR-2026-000001', $draft->receiptNumber());
        $this->assertNotNull($draft->amountInWords());
    }

    public function testDraftReturnsFailureForNotFoundPayment(): void
    {
        $txnId = new Identifier(\App\Shared\Support\UlidGenerator::generate());
        $this->payments->method('findById')->willReturn(null);

        $result = $this->renderer->draft($txnId);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('not found', strtolower($result->error() ?? ''));
    }

    public function testDraftReturnsFailureForNonSuccessfulPayment(): void
    {
        $txnId = new Identifier(\App\Shared\Support\UlidGenerator::generate());
        $paymentId = EntityId::generate('payment');
        $payment = $this->makePayment($paymentId, TransactionStatus::FAILED);

        $this->payments->method('findById')->willReturn($payment);

        $result = $this->renderer->draft($txnId);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('status', strtolower($result->error() ?? ''));
    }

    public function testDraftIsIdempotentWhenReceiptExists(): void
    {
        $txnId = new Identifier(\App\Shared\Support\UlidGenerator::generate());
        $paymentId = EntityId::generate('payment');
        $existingReceipt = $this->makeReceipt($paymentId);

        $this->payments->method('findById')->willReturn($this->makePayment($paymentId, TransactionStatus::CAPTURED));
        $this->receipts->method('existsForTransaction')->willReturn(true);
        $this->receipts->method('findByTransactionId')->willReturn($existingReceipt);

        // None of the expensive operations should be called when returning existing
        $this->allocator->expects($this->never())->method('next');
        $this->pdfGenerator->expects($this->never())->method('render');

        $result = $this->renderer->draft($txnId);

        $this->assertTrue($result->isOk());
    }

    public function testGenerateAdapterMapsDraftToArrayShape(): void
    {
        $txnId = new Identifier(\App\Shared\Support\UlidGenerator::generate());
        $paymentId = EntityId::generate('payment');
        $donationId = EntityId::generate('donation');
        $fileAsset = $this->makeFileAssetRecord();

        $payment = $this->makePayment($paymentId, TransactionStatus::CAPTURED);
        $donation = $this->makeDonation($donationId);

        $this->payments->method('findById')->willReturn($payment);
        $this->receipts->method('existsForTransaction')->willReturn(false);
        $this->donations->method('findById')->willReturn($donation);
        $this->allocator->method('next')->willReturn('TR-2026-000099');
        $this->validator80G->method('isEligible')->willReturn(Result::success([
            'eligible' => false, 'reason' => null,
            'certificate_required' => false, 'certificate_number' => null,
        ]));
        $this->pdfGenerator->method('render')->willReturn(Result::success('%PDF'));
        $this->storage->method('persist')->willReturn(Result::success($fileAsset));

        $result = $this->renderer->generate($txnId);

        $this->assertTrue($result->isOk());
        $array = $result->value();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('receipt_number', $array);
        $this->assertSame('TR-2026-000099', $array['receipt_number']);
        $this->assertArrayHasKey('issued_at', $array);
        $this->assertArrayHasKey('download_url', $array);
        $this->assertArrayHasKey('content_hash', $array);
    }

    public function testReceiptNumberMatchingExpectedRegex(): void
    {
        $txnId = new Identifier(\App\Shared\Support\UlidGenerator::generate());
        $paymentId = EntityId::generate('payment');
        $fileAsset = $this->makeFileAssetRecord();

        $this->payments->method('findById')->willReturn($this->makePayment($paymentId, TransactionStatus::CAPTURED));
        $this->receipts->method('existsForTransaction')->willReturn(false);
        $this->donations->method('findById')->willReturn($this->makeDonation(EntityId::generate('donation')));
        $this->campaigns->method('findById')->willReturn(null);
        $this->allocator->method('next')->willReturn('TR-2026-000042');
        $this->validator80G->method('isEligible')->willReturn(Result::success([
            'eligible' => false, 'reason' => null,
            'certificate_required' => false, 'certificate_number' => null,
        ]));
        $this->pdfGenerator->method('render')->willReturn(Result::success('%PDF'));
        $this->storage->method('persist')->willReturn(Result::success($fileAsset));

        $result = $this->renderer->draft($txnId);

        $this->assertTrue($result->isOk());
        $draft = $result->value();
        $this->assertMatchesRegularExpression('/^TR-\d{4}-\d{6}(-[A-Za-z0-9_-]+)?$/', $draft->receiptNumber());
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    private function makePayment(EntityId $id, TransactionStatus $status): Payment
    {
        // Walk the FSM to reach the requested status from INITIALIZED.
        // Statuses other than CAPTURED need manual mapping because the
        // happy-path FSM stops at CAPTURED; we set them via direct
        // transitionTo calls, with failure paths using GATEWAY_FAILED.
        $payment = Payment::initialize(
            donationId: EntityId::generate('donation'),
            providerCode: \App\Payments\Domain\Enums\PaymentProvider::RAZORPAY,
            amountMinor: 5_000_00,
            currency: Currency::INR,
            idempotencyKey: $id->ulid(),
            metadata: [],
        );

        if ($status === TransactionStatus::CAPTURED) {
            $machine = new \App\Payments\Domain\StateMachines\PaymentStateMachine();
            $payment = $payment->transitionTo($machine, TransactionStatus::PENDING);
            $payment = $payment->transitionTo($machine, TransactionStatus::AUTHORIZED);
            $payment = $payment->transitionTo($machine, TransactionStatus::CAPTURED, [
                'amount_minor' => 5_000_00,
            ]);
        }

        return $payment;
    }

    private function makeDonation(EntityId $id): Donation
    {
        $donor = \App\Payments\Domain\ValueObjects\DonorIdentity::identified(
            name: 'Test Donor',
            email: 'donor@test.com',
            phone: null,
            pan: null,
            address: null,
        );

        return Donation::draft(
            campaignId: EntityId::generate('campaign'),
            donor: $donor,
            amountMinor: 5_000_00,
            currency: Currency::INR,
            id: $id,
        );
    }

    private function makeReceipt(EntityId $paymentId): Receipt
    {
        return Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: $paymentId,
            campaignId: EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000001',
            campaignTitleSnapshot: 'Test',
            donorName: 'Test Donor',
            amountMinor: 5_000_00,
            currency: Currency::INR,
            contentHash: hash('sha256', 'existing'),
            receiptFileId: EntityId::generate('file_asset'),
        );
    }

    private function makeFileAssetRecord(): \App\Payments\Domain\ValueObjects\FileAssetRecord
    {
        return \App\Payments\Domain\ValueObjects\FileAssetRecord::create(
            id: EntityId::generate('file_asset')->value(),
            ownerType: 'receipt',
            ownerId: EntityId::generate('receipt')->value(),
            originalFilename: 'receipt.pdf',
            storageDisk: 'local',
            storagePath: 'receipts/2026/TR-2026-000001.pdf',
            mimeType: 'application/pdf',
            fileSizeBytes: 12345,
            fileHashSha256: hash('sha256', 'test-content'),
            purpose: 'receipt_pdf',
        );
    }
}
