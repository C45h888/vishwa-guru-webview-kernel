<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Infrastructure\Receipts\Form10BDExporter;
use App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Clock;
use App\Shared\Support\FrozenClock;
use App\Shared\Support\Result;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class Form10BDExporterTest extends TestCase
{
    private ReceiptRepositoryContract $receipts;
    private DonationRepositoryContract $donations;
    private Clock $clock;
    private ConfigurationContract $config;
    private Form10BDExporter $exporter;

    protected function setUp(): void
    {
        $this->receipts = $this->createMock(ReceiptRepositoryContract::class);
        $this->donations = $this->createMock(DonationRepositoryContract::class);
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-07-16T12:00:00+05:30'));
        $this->config = $this->createMock(ConfigurationContract::class);
        $this->config->method('get')->willReturnCallback(fn($k, $d = null) => $d);
        $this->config->method('integer')->willReturn(50_00);

        $this->exporter = new Form10BDExporter(
            $this->receipts,
            $this->donations,
            $this->clock,
            $this->config,
        );
    }

    public function testExportIncludesEligibleReceiptsFromDateRange(): void
    {
        // The contract: Form10BDExporter.export(date range) must reach the
        // receipt repository via findByDateRange(), filter by 80G eligibility
        // and minimum amount, and emit one CSV row per surviving receipt.
        $quarterStart = new DateTimeImmutable('2026-04-01');
        $quarterEnd = new DateTimeImmutable('2026-06-30');

        $eligible = $this->createMock(Receipt::class);
        $eligible->method('tax80gEligible')->willReturn(true);
        $eligible->method('amountMinor')->willReturn(50_000);        // ₹500 → above ₹500 threshold tied
        $eligible->method('donorName')->willReturn('Eligible Donor');
        $eligible->method('donorPan')->willReturn('ABCDE1234F');
        $eligible->method('generatedAt')->willReturn(new DateTimeImmutable('2026-05-15'));
        $eligible->method('amountInWords')->willReturn('Five Hundred Rupees Only');

        $notEligible = $this->createMock(Receipt::class);
        $notEligible->method('tax80gEligible')->willReturn(false);
        $notEligible->method('amountMinor')->willReturn(1_000_000);

        $this->receipts
            ->expects($this->once())
            ->method('findByDateRange')
            ->with(
                $this->equalTo($quarterStart),
                $this->equalTo($quarterEnd),
            )
            ->willReturn([$eligible, $notEligible]);

        $donation = $this->createMock(Donation::class);
        $donation->method('donorAddressSnapshot')->willReturn(null);
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
        $this->assertStringContainsString('15/05/2026', $csv);
        // 80G-ineligible receipt should not appear
        $this->assertStringNotContainsString('Not In CSV', $csv);
    }

    public function testExportSkipsReceiptsBelowMinimumAmount(): void
    {
        $quarterStart = new DateTimeImmutable('2026-04-01');
        $quarterEnd = new DateTimeImmutable('2026-06-30');

        $smallReceipt = $this->createMock(Receipt::class);
        $smallReceipt->method('tax80gEligible')->willReturn(true);
        $smallReceipt->method('amountMinor')->willReturn(40_00);     // ₹400 → below ₹500 threshold

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
                '1',
                'Temple Trust',
                '123 Temple St',
                'AAACT1234D',
                'REG1234',
                'John Doe',
                '456 Main St',
                'ABCDE1234F',
                'Others',
                '5000.00',
                '15/06/2026',
                'Five Thousand Rupees Only',
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
        $receipt = $this->createMock(Receipt::class);
        $receipt->method('donorName')->willReturn('Jane Doe');
        $receipt->method('donorPan')->willReturn('XYZPA1234M');
        $receipt->method('amountMinor')->willReturn(10_000_00);
        $receipt->method('generatedAt')->willReturn(new DateTimeImmutable('2026-05-15'));
        $receipt->method('amountInWords')->willReturn('Ten Thousand Rupees Only');

        $donation = $this->createMock(Donation::class);
        $donation->method('donorAddressSnapshot')->willReturn([
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
        $this->assertSame('789 Donor Lane, Pune, MH 411001', $row[6]);
        $this->assertSame('XYZPA1234M', $row[7]);
        $this->assertSame('10000.00', $row[9]);
        $this->assertSame('15/05/2026', $row[10]);
    }
}
