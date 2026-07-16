<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Services;

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
use App\Payments\Services\FailureStateService;
use App\Payments\Services\ReceiptService;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Verifies ReceiptService correctly consumes the Pass 1.7 draft() method
 * and builds Receipt::issue() from the ReceiptDraft carrier.
 */
class ReceiptServicePass17Test extends TestCase
{
    private ReceiptGenerationContract $generator;
    private ReceiptRepositoryContract $receipts;
    private PaymentRepositoryContract $payments;
    private DonationRepositoryContract $donations;
    private FailureStateService $failureStateService;
    private Clock $clock;
    private ReceiptService $service;

    protected function setUp(): void
    {
        $this->generator = $this->createMock(ReceiptGenerationContract::class);
        $this->receipts = $this->createMock(ReceiptRepositoryContract::class);
        $this->payments = $this->createMock(PaymentRepositoryContract::class);
        $this->donations = $this->createMock(DonationRepositoryContract::class);
        $this->failureStateService = $this->createMock(FailureStateService::class);
        $this->clock = new \App\Shared\Support\FrozenClock(new DateTimeImmutable());

        $stateMachine = new ReceiptStateMachine();

        $this->service = new ReceiptService(
            $this->receipts,
            $this->payments,
            $this->donations,
            $this->failureStateService,
            $this->generator,
            $stateMachine,
            $this->clock,
        );
    }

    public function testIssueCallsDraftMethodNotGenerate(): void
    {
        $txnId = new Identifier('txn_pass17_001');
        $paymentId = EntityId::generate('payment');
        $donationId = EntityId::generate('donation');
        $fileAssetId = EntityId::generate('file_asset');

        $draft = $this->makeDraft($txnId, $donationId, $fileAssetId);

        // Verify draft() is called, not generate()
        $calledMethods = [];
        $this->generator->method('draft')
            ->willReturnCallback(function () use (&$calledMethods, $draft) {
                $calledMethods[] = 'draft';
                return Result::success($draft);
            });

        $this->generator->expects($this->never())->method('generate');

        $this->payments->method('findById')->willReturn($this->makePayment($paymentId));
        $this->donations->method('findById')->willReturn($this->makeDonation($donationId));

        $result = $this->service->issue($txnId);

        $this->assertTrue($result->isOk());
        $this->assertContains('draft', $calledMethods);
    }

    public function testIssueConsumesReceiptDraftFromRenderer(): void
    {
        $txnId = new Identifier('txn_pass17_002');
        $paymentId = EntityId::generate('payment');
        $donationId = EntityId::generate('donation');
        $fileAssetId = EntityId::generate('file_asset');

        $draft = $this->makeDraft($txnId, $donationId, $fileAssetId);

        $this->generator->method('draft')->willReturn(Result::success($draft));
        $this->payments->method('findById')->willReturn($this->makePayment($paymentId));
        $this->donations->method('findById')->willReturn($this->makeDonation($donationId));

        $result = $this->service->issue($txnId);

        $this->assertTrue($result->isOk());

        /** @var Receipt $receipt */
        $receipt = $result->value();

        $this->assertSame('TR-2026-000042', $receipt->receiptNumber());
        $this->assertNotNull($receipt->receiptFileId());
        $this->assertNotEmpty($receipt->contentHash());
    }

    public function testIssuePersistsReceiptBuiltFromDraft(): void
    {
        $txnId = new Identifier('txn_pass17_003');
        $paymentId = EntityId::generate('payment');
        $donationId = EntityId::generate('donation');
        $fileAssetId = EntityId::generate('file_asset');

        $draft = $this->makeDraft($txnId, $donationId, $fileAssetId);

        $savedReceipts = [];
        $this->receipts->method('save')
            ->willReturnCallback(function (Receipt $r) use (&$savedReceipts) {
                $savedReceipts[] = $r;
            });

        $this->generator->method('draft')->willReturn(Result::success($draft));
        $this->payments->method('findById')->willReturn($this->makePayment($paymentId));
        $this->donations->method('findById')->willReturn($this->makeDonation($donationId));

        $result = $this->service->issue($txnId);

        $this->assertTrue($result->isOk());
        $this->assertCount(1, $savedReceipts);
        $this->assertSame($savedReceipts[0]->receiptNumber(), 'TR-2026-000042');
    }

    public function testIssueReturnsFailureWhenDraftFails(): void
    {
        $txnId = new Identifier('txn_pass17_fail');
        $paymentId = EntityId::generate('payment');

        $this->generator->method('draft')
            ->willReturn(Result::failure('PDF rendering failed'));

        $this->payments->method('findById')->willReturn($this->makePayment($paymentId));

        // Expect escalation
        $this->failureStateService->expects($this->once())
            ->method('record');

        $result = $this->service->issue($txnId);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('PDF rendering failed', $result->error() ?? '');
    }

    // ─── Fixtures ─────────────────────────────────────────────────────

    private function makeDraft(Identifier $txnId, EntityId $donationId, EntityId $fileAssetId): ReceiptDraft
    {
        return ReceiptDraft::fromRenderer(
            transactionId: $txnId,
            donationId: new Identifier($donationId->value()),
            receiptNumber: 'TR-2026-000042',
            fileAssetId: new Identifier($fileAssetId->value()),
            issuedAt: new DateTimeImmutable('2026-07-16T12:00:00+05:30'),
            contentHash: hash('sha256', 'test-receipt-content'),
            amountInWords: 'One Lakh Rupees Only',
            deliveryChannel: null,
            deliveryAddress: null,
        );
    }

    private function makePayment(EntityId $id): Payment
    {
        $mock = $this->createMock(Payment::class);
        $mock->method('id')->willReturn($id);
        $mock->method('donationId')->willReturn(EntityId::generate('donation'));
        $mock->method('status')->willReturn(TransactionStatus::CAPTURED);
        $mock->method('amountMinor')->willReturn(1_00_000_00);
        $mock->method('currency')->willReturn(Currency::INR);

        return $mock;
    }

    private function makeDonation(EntityId $id): Donation
    {
        $mock = $this->createMock(Donation::class);
        $mock->method('id')->willReturn($id);
        $mock->method('campaignId')->willReturn(EntityId::generate('campaign'));
        $mock->method('donorNameSnapshot')->willReturn('Test Donor');
        $mock->method('donorEmailSnapshot')->willReturn('donor@test.com');
        $mock->method('donorPanSnapshot')->willReturn('ABCTY1234D');
        $mock->method('donorAddressSnapshot')->willReturn(null);

        return $mock;
    }
}
