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

    public function testExportReturnsEmptyCsvWhenNoEligibleReceipts(): void
    {
        // collectEligibleReceipts currently returns empty (no date-range query yet)
        // This test documents the expected behavior once the query is wired
        $this->markTestSkipped(
            'collectEligibleReceipts needs date-range query on ReceiptRepository ' .
            'before this integration test can run end-to-end. ' .
            'Wired in Pass 1.4 when findByDateRange is added.',
        );
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
