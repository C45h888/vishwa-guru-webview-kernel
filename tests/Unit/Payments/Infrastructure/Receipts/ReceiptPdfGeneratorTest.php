<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper;
use App\Payments\Infrastructure\Receipts\ReceiptFormatter;
use App\Payments\Infrastructure\Receipts\ReceiptPdfGenerator;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Result;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\View\View;
use PHPUnit\Framework\TestCase;

class ReceiptPdfGeneratorTest extends TestCase
{
    private PdfWrapper $pdf;
    private ReceiptFormatter $formatter;
    private ViewFactory $views;
    private ConfigurationContract $config;
    private ReceiptPdfGenerator $generator;

    protected function setUp(): void
    {
        $this->pdf = $this->createMock(PdfWrapper::class);
        $this->formatter = new ReceiptFormatter();
        $this->views = $this->createMock(ViewFactory::class);
        $this->config = $this->createMock(ConfigurationContract::class);

        $this->config->method('get')->willReturnCallback(fn($k, $d = null) => $d);

        $this->generator = new ReceiptPdfGenerator(
            $this->pdf,
            $this->formatter,
            $this->views,
            $this->config,
        );
    }

    public function testRenderReturnsPdfBytesOnSuccess(): void
    {
        $receipt = $this->makeReceipt();
        $payment = $this->makePayment();
        $donation = $this->makeDonation();

        $mockView = $this->createMock(View::class);
        $mockView->method('render')->willReturn('<html><body>Mock Receipt</body></html>');

        $this->views->method('make')->willReturn($mockView);

        $this->pdf->method('render')
            ->willReturn("%PDF-1.4\nmock-content\n%%EOF");

        $result = $this->generator->render($receipt, $payment, $donation);

        $this->assertTrue($result->isOk());
        $this->assertStringStartsWith('%PDF', $result->value());
    }

    public function testRenderReturnsFailureOnEmptyOutput(): void
    {
        $receipt = $this->makeReceipt();
        $payment = $this->makePayment();
        $donation = $this->makeDonation();

        $mockView = $this->createMock(View::class);
        $mockView->method('render')->willReturn('<html><body>content</body></html>');

        $this->views->method('make')->willReturn($mockView);
        $this->pdf->method('render')->willReturn('');

        $result = $this->generator->render($receipt, $payment, $donation);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('empty', strtolower($result->error() ?? ''));
    }

    public function testRenderPassesFormattedDataToView(): void
    {
        $receipt = $this->makeReceipt();
        $payment = $this->makePayment();
        $donation = $this->makeDonation();

        $capturedData = [];
        $mockView = $this->createMock(View::class);
        $mockView->method('render')->willReturn('<html></html>');
        $mockView->method('with')->willReturnCallback(function ($key, $val) use (&$capturedData) {
            $capturedData[$key] = $val;
            return $mockView;
        });

        $this->views->method('make')
            ->willReturnCallback(function ($view, $data) use (&$capturedData, $mockView) {
                $capturedData = is_array($data) ? $data : [];
                return $mockView;
            });

        $this->pdf->method('render')->willReturn('%PDF-1.4');

        $this->generator->render($receipt, $payment, $donation);

        $this->assertArrayHasKey('receipt_number', $capturedData);
        $this->assertArrayHasKey('trust_name', $capturedData);
        $this->assertArrayHasKey('donor_name', $capturedData);
        $this->assertArrayHasKey('amount_in_words', $capturedData);
    }

    // ─── Fixtures ────────────────────────────────────────────────────────

    private function makeReceipt(): Receipt
    {
        return Receipt::issue(
            donationId: \App\Persistence\ValueObjects\EntityId::generate('donation'),
            paymentId: \App\Persistence\ValueObjects\EntityId::generate('payment'),
            campaignId: \App\Persistence\ValueObjects\EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000001',
            campaignTitleSnapshot: 'Test Campaign',
            donorName: 'Test Donor',
            amountMinor: 5_000_00,
            currency: Currency::INR,
            contentHash: hash('sha256', 'test'),
            donorEmail: 'donor@test.com',
            donorPan: null,
            donorAddress: null,
            amountInWords: 'Five Thousand Rupees Only',
            isTaxDeductible: false,
            tax80gEligible: false,
            receiptFileId: \App\Persistence\ValueObjects\EntityId::generate('file_asset'),
        );
    }

    private function makePayment(): Payment
    {
        return Payment::create(
            donationId: \App\Persistence\ValueObjects\EntityId::generate('donation'),
            gatewayTransactionId: 'txn_test',
            amountMinor: 5_000_00,
            currency: Currency::INR,
            status: TransactionStatus::CAPTURED,
            gatewayCode: 'razorpay',
            metadata: [],
        );
    }

    private function makeDonation(): Donation
    {
        return Donation::create(
            donorId: \App\Persistence\ValueObjects\EntityId::generate('donor'),
            campaignId: \App\Persistence\ValueObjects\EntityId::generate('campaign'),
            amountMinor: 5_000_00,
            currency: Currency::INR,
            donorNameSnapshot: 'Test Donor',
            donorEmailSnapshot: 'donor@test.com',
            donorPhoneSnapshot: null,
            donorPanSnapshot: null,
            donorAddressSnapshot: null,
            dedication: null,
            donorMessage: null,
            metadata: [],
        );
    }
}
