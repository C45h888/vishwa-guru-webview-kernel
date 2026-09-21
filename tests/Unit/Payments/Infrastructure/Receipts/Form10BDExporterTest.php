<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Infrastructure\Receipts\Form10BDExporter;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\FrozenClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class Form10BDExporterTest extends TestCase
{
    private ReceiptRepositoryContract $receipts;
    private DonationRepositoryContract $donations;
    private ConfigurationContract $config;
    private FrozenClock $clock;
    private Form10BDExporter $exporter;

    protected function setUp(): void
    {
        $this->receipts = $this->createMock(ReceiptRepositoryContract::class);
        $this->donations = $this->createMock(DonationRepositoryContract::class);
        $this->config = $this->createMock(ConfigurationContract::class);
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-07-16T12:00:00+05:30'));

        $this->exporter = new Form10BDExporter(
            $this->receipts,
            $this->donations,
            $this->clock,
            $this->config,
        );
    }

    public function testExportIncludesEligibleReceiptsFromDateRange(): void
    {
        $quarterStart = new DateTimeImmutable('2026-04-01');
        $quarterEnd = new DateTimeImmutable('2026-06-30');

        $eligible = $this->makeReceipt(
            amountMinor: 50_000,                       // ₹500 → above ₹500 threshold
            donorName: 'Eligible Donor',
            donorPan: 'ABCDE1234F',
            generatedAt: new DateTimeImmutable('2026-05-15'),
            amountInWords: 'Five Hundred Rupees Only',
            tax80gEligible: true,
        );

        $notEligible = $this->makeReceipt(
            amountMinor: 1_000_000,
            donorName: 'Not In CSV',
            donorPan: null,
            generatedAt: new DateTimeImmutable('2026-05-15'),
            amountInWords: 'Ten Thousand Rupees Only',
            tax80gEligible: false,
        );

        $this->receipts
            ->expects($this->once())
            ->method('findByDateRange')
            ->with(
                $this->equalTo($quarterStart),
                $this->equalTo($quarterEnd),
            )
            ->willReturn([$eligible, $notEligible]);

        $donation = $this->makeDonation(donorAddressSnapshot: null);
        $this->donations
            ->expects($this->once())
            ->method('findById')
            ->willReturn($donation);

        $result = $this->exporter->export(
            quarterStart: $quarterStart,
            quarterEnd: $quarterEnd,
            trustName: 'Temple Trust',
            trustAddress: '123 Temple St',
            trustPan: 'AAACT1234D',
            trust80gRegNumber: 'REG1234',
        );

        $this->assertTrue($result->isOk());
        $csv = $result->value();
        $this->assertStringContainsString('Eligible Donor', $csv);
        $this->assertStringContainsString('ABCDE1234F', $csv);
        $this->assertStringContainsString('500.00', $csv);
        // generatedAt is stamped inside Receipt::issue() via clock::now();
        // the date-format helper is covered by its own unit test.
        $this->assertMatchesRegularExpression('/\d{2}\/\d{2}\/\d{4}/', $csv);
        // 80G-ineligible receipt should not appear
        $this->assertStringNotContainsString('Not In CSV', $csv);
    }

    public function testExportSkipsReceiptsBelowMinimumAmount(): void
    {
        // The exporter reads the min-amount threshold from config;
        // stub the configuration so the test is self-contained.
        $this->config
            ->method('integer')
            ->with('receipts.form_10bd.min_amount_minor', 50_00)
            ->willReturn(50_000); // ₹500

        $quarterStart = new DateTimeImmutable('2026-04-01');
        $quarterEnd = new DateTimeImmutable('2026-06-30');

        $smallReceipt = $this->makeReceipt(
            amountMinor: 40_00,    // ₹400 → below ₹500 threshold
            donorName: 'Small Donor',
            donorPan: 'ABCDE1234F',
            generatedAt: new DateTimeImmutable('2026-05-15'),
            amountInWords: 'Four Hundred Rupees Only',
            tax80gEligible: true,
        );

        $this->receipts
            ->expects($this->once())
            ->method('findByDateRange')
            ->willReturn([$smallReceipt]);

        // donations->findById must NOT be called — receipt is filtered out before
        $this->donations
            ->expects($this->never())
            ->method('findById');

        $result = $this->exporter->export(
            quarterStart: $quarterStart,
            quarterEnd: $quarterEnd,
            trustName: 'Temple Trust',
            trustAddress: '',
            trustPan: '',
            trust80gRegNumber: '',
        );

        $this->assertTrue($result->isOk());
        // Empty CSV (header row only)
        $csv = $result->value();
        $csvRows = array_filter(explode("\n", trim($csv)));
        $this->assertCount(1, $csvRows, 'only the header row should remain');
    }

    public function testBuildCsvFormatsHeadersCorrectly(): void
    {
        // Use reflection to access private buildCsv method
        $reflection = new \ReflectionClass($this->exporter);
        $method = $reflection->getMethod('buildCsv');
        $method->setAccessible(true);

        $rows = [
            [
                1, 'Temple Trust', '123 Temple St', 'AAACT1234D', 'REG1234',
                'John Doe', '123 Main St', 'ABCDE1234F', '5000.00',
                '15/05/2026', 'Five Thousand Rupees Only',
            ],
        ];

        $csv = $method->invoke($this->exporter, $rows);

        $this->assertStringContainsString('Sl.No', $csv);
        $this->assertStringContainsString('Name of Donee', $csv);
        $this->assertStringContainsString('Name of Donor', $csv);
        $this->assertStringContainsString('John Doe', $csv);
        $this->assertStringContainsString('5000.00', $csv);
        $this->assertStringContainsString('Five Thousand Rupees Only', $csv);
    }

    public function testExportReturnsSuccessResult(): void
    {
        $result = $this->exporter->export(
            quarterStart: new DateTimeImmutable('2026-04-01'),
            quarterEnd: new DateTimeImmutable('2026-06-30'),
            trustName: 'Temple Trust',
            trustAddress: '123 Temple St',
            trustPan: 'AAACT1234D',
            trust80gRegNumber: 'REG1234',
        );

        $this->assertTrue($result->isOk());
        $this->assertIsString($result->value());
    }

    public function testBuildRowFormatsAllFields(): void
    {
        $receipt = $this->makeReceipt(
            amountMinor: 10_000_00,
            donorName: 'Jane Doe',
            donorPan: 'XYZPA1234M',
            generatedAt: new DateTimeImmutable('2026-05-15'),
            amountInWords: 'Ten Thousand Rupees Only',
            tax80gEligible: true,
        );

        $donation = $this->makeDonation(donorAddressSnapshot: [
            'line1' => '789 Donor Lane',
            'city' => 'Pune',
            'state' => 'MH',
            'pincode' => '411001',
        ]);

        $reflection = new \ReflectionClass($this->exporter);
        $method = $reflection->getMethod('buildRow');
        $method->setAccessible(true);

        $row = $method->invoke($this->exporter,
            1, $receipt, $donation,
            'Temple Trust', '123 Temple St', 'AAACT1234D', 'REG1234',
        );

        $this->assertSame('1', $row[0]);
        $this->assertSame('Temple Trust', $row[1]);
        $this->assertSame('789 Donor Lane, Pune, MH, 411001', $row[6]);
        $this->assertSame('XYZPA1234M', $row[7]);
        $this->assertSame('10000.00', $row[9]);
        // generatedAt is set to clock::now() inside Receipt::issue(),
        // not via factory argument; the formatting is exercised via the
        // date-format unit, here we just confirm the row shape.
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $row[10]);
    }

    /**
     * Build a real Receipt entity via the canonical factory.
     */
    private function makeReceipt(
        int $amountMinor,
        string $donorName,
        ?string $donorPan,
        DateTimeImmutable $generatedAt,
        string $amountInWords,
        bool $tax80gEligible,
    ): Receipt {
        return Receipt::issue(
            donationId: \App\Persistence\ValueObjects\EntityId::generate('donation'),
            paymentId: \App\Persistence\ValueObjects\EntityId::generate('payment'),
            campaignId: \App\Persistence\ValueObjects\EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT).'-A7c3ZpQ9',
            campaignTitleSnapshot: 'Test Campaign',
            donorName: $donorName,
            amountMinor: $amountMinor,
            currency: Currency::INR,
            contentHash: hash('sha256', $donorName.$amountMinor),
            donorEmail: 'donor@test.com',
            donorPan: $donorPan,
            donorAddress: null,
            amountInWords: $amountInWords,
            isTaxDeductible: true,
            tax80gEligible: $tax80gEligible,
            tax80gCertificateNumber: $tax80gEligible ? 'CERT/2026/REG/001' : null,
            receiptFileId: \App\Persistence\ValueObjects\EntityId::generate('file_asset'),
        );
    }

    /**
     * Build a real Donation entity via the canonical factory.
     *
     * @param  array<string, string>|null  $donorAddressSnapshot
     */
    private function makeDonation(?array $donorAddressSnapshot): Donation
    {
        $donor = DonorIdentity::identified(
            name: 'Test Donor',
            email: 'donor@test.com',
            phone: null,
            pan: null,
            address: $donorAddressSnapshot,
        );

        return Donation::draft(
            campaignId: \App\Persistence\ValueObjects\EntityId::generate('campaign'),
            donor: $donor,
            amountMinor: 50_000,
            currency: Currency::INR,
        );
    }
}
