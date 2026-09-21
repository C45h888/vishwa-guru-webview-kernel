<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Infrastructure\Receipts\ReceiptFormatter;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReceiptFormatterTest extends TestCase
{
    private ReceiptFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ReceiptFormatter();
    }

    public function testFormatIncludesAllRequiredFields(): void
    {
        $receipt = $this->makeReceipt();
        $payment = $this->makePayment();
        $donation = $this->makeDonation();

        $data = $this->formatter->format($receipt, $payment, $donation, [
            'name' => 'Temple Trust',
            'address' => '123 Temple St',
            'email' => 'trust@example.com',
            'phone' => '+91-98765-43210',
            'pan' => 'AAACT1234D',
        ]);

        $this->assertSame('Temple Trust', $data['trust_name']);
        $this->assertSame('123 Temple St', $data['trust_address']);
        $this->assertSame('trust@example.com', $data['trust_email']);
        $this->assertSame('+91-98765-43210', $data['trust_phone']);
        $this->assertSame('AAACT1234D', $data['trust_pan']);
        $this->assertSame('TR-2026-000042', $data['receipt_number']);
        $this->assertSame('One Lakh Twenty Three Thousand Rupees Only', $data['amount_in_words']);
        $this->assertSame('R', $data['donor_name']);
        $this->assertSame('donor@example.com', $data['donor_email']);
        $this->assertSame('ABCPY1234D', $data['donor_pan']);
    }

    public function testFormatIncludesAmountInWords(): void
    {
        $receipt = $this->makeReceipt();
        $payment = $this->makePayment();
        $donation = $this->makeDonation();

        $data = $this->formatter->format($receipt, $payment, $donation);

        $this->assertNotEmpty($data['amount_in_words']);
        $this->assertStringContainsString('Rupees', $data['amount_in_words']);
    }

    public function testFormatShowsAddressBlockWhenProvided(): void
    {
        $receipt = $this->makeReceipt();
        $payment = $this->makePayment();
        $donation = $this->makeDonation();

        $data = $this->formatter->format($receipt, $payment, $donation);

        $this->assertStringContainsString('123 Main St', $data['donor_address']);
    }

    public function testFormatOmitsOptionalFieldsWhenNull(): void
    {
        $receipt = Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: EntityId::generate('payment'),
            campaignId: EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000001',
            campaignTitleSnapshot: 'Test Campaign',
            donorName: 'Anonymous Donor',
            amountMinor: 500_00,
            currency: Currency::INR,
            contentHash: hash('sha256', 'test'),
            donorEmail: null,
            donorPan: null,
            donorAddress: null,
            isTaxDeductible: false,
            tax80gEligible: false,
            receiptFileId: null,
        );

        $payment = $this->makePayment();
        $donation = $this->makeDonation();

        $data = $this->formatter->format($receipt, $payment, $donation);

        $this->assertNull($data['donor_email']);
        $this->assertNull($data['donor_pan']);
    }

    public function testBuildAddressBlockWithFullAddress(): void
    {
        $block = $this->formatter->buildAddressBlock([
            'line1' => '123 Main Street',
            'line2' => 'Apt 4B',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'country' => 'India',
        ]);

        $this->assertStringContainsString('123 Main Street', $block);
        $this->assertStringContainsString('Apt 4B', $block);
        $this->assertStringContainsString('Mumbai', $block);
        $this->assertStringContainsString('400001', $block);
    }

    public function testBuildAddressBlockWithMinimalAddress(): void
    {
        $block = $this->formatter->buildAddressBlock(['city' => 'Bangalore']);

        $this->assertSame('Bangalore', $block);
    }

    public function testBuildAddressBlockReturnsEmptyForNull(): void
    {
        $this->assertSame('', $this->formatter->buildAddressBlock(null));
        $this->assertSame('', $this->formatter->buildAddressBlock([]));
    }

    public function testFormatSummary(): void
    {
        $receipt = $this->makeReceipt();

        $summary = $this->formatter->formatSummary($receipt);

        $this->assertSame('TR-2026-000042', $summary['receipt_number']);
        $this->assertSame('R', $summary['donor_name']);
        $this->assertStringContainsString('₹', $summary['amount_display']);
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    private function makeReceipt(): Receipt
    {
        return Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: EntityId::generate('payment'),
            campaignId: EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000042',
            campaignTitleSnapshot: 'General Fund',
            donorName: 'R',
            amountMinor: 1_23_456_00, // ₹1,23,456
            currency: Currency::INR,
            contentHash: hash('sha256', 'test'),
            donorEmail: 'donor@example.com',
            donorPan: 'ABCPY1234D',
            donorAddress: [
                'line1' => '123 Main St',
                'city' => 'Mumbai',
                'state' => 'MH',
                'pincode' => '400001',
            ],
            amountInWords: 'One Lakh Twenty Three Thousand Rupees Only',
            isTaxDeductible: true,
            tax80gEligible: true,
            tax80gCertificateNumber: '80G/2026/REG/ABC123',
            receiptFileId: EntityId::generate('file_asset'),
        );
    }

    private function makePayment(): Payment
    {
        $payment = Payment::initialize(
            donationId: EntityId::generate('donation'),
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 1_23_456_00,
            currency: Currency::INR,
            idempotencyKey: 'txn_123',
            metadata: [],
        );

        // Walk FSM: INITIALIZED → PENDING → AUTHORIZED → CAPTURED
        $machine = new \App\Payments\Domain\StateMachines\PaymentStateMachine();
        $payment = $payment->transitionTo($machine, TransactionStatus::PENDING);
        $payment = $payment->transitionTo($machine, TransactionStatus::AUTHORIZED);
        $payment = $payment->transitionTo($machine, TransactionStatus::CAPTURED, [
            'amount_minor' => 1_23_456_00,
        ]);

        return $payment;
    }

    private function makeDonation(): Donation
    {
        // DonorIdentity::anonymous() when donor is anonymous;
        // DonorIdentity::identified() when named. Use the named form so
        // snapshots are populated for the formatter tests.
        $donor = \App\Payments\Domain\ValueObjects\DonorIdentity::identified(
            name: 'R',
            email: 'donor@example.com',
            phone: null,
            pan: 'ABCPY1234D',
            address: [
                'line1' => '123 Main St',
                'city' => 'Mumbai',
                'state' => 'MH',
                'pincode' => '400001',
            ],
        );

        return Donation::draft(
            campaignId: EntityId::generate('campaign'),
            donor: $donor,
            amountMinor: 1_23_456_00,
            currency: Currency::INR,
        );
    }
}
