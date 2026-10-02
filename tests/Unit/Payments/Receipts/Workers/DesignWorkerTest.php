<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Receipts\Workers;

use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Infrastructure\Receipts\Pdf\InMemoryPdfWrapper;
use App\Payments\Receipts\Workers\DesignWorker;
use Tests\TestCase;

/**
 * DesignWorkerTest — the design file system's compile boundary.
 *
 * Uses the REAL Blade templates (resources/views/receipts/design/) so a
 * template that references a field the typed document doesn't carry
 * fails here, not at issuance time.
 */
final class DesignWorkerTest extends TestCase
{
    private function makeDocument(): ReceiptDocument
    {
        return new ReceiptDocument(
            receiptNumber: 'TR-2026-000042-A7c3ZpQ9',
            fyLabel: 'FY 2026-27',
            campaignTitle: 'Temple Renovation',
            donorName: 'Priya Sharma',
            donorEmail: 'priya@example.in',
            donorPan: 'ABCTY1234D',
            donorAddressBlock: "12 Temple Lane\nMumbai, MH 400001",
            amountMinor: 5_000_00,
            currency: Currency::INR,
            amountDisplay: '₹5,000.00',
            amountInWords: 'Five Thousand Rupees Only',
            paymentReference: 'payment_01ARZ3NDEKTSV4RRFFQ69G5FAV',
            paymentDate: new \DateTimeImmutable('2026-07-15T10:00:00+05:30'),
            paymentDateDisplay: '15 July 2026',
            generatedAt: new \DateTimeImmutable('2026-07-16T12:00:00+05:30'),
            issuedDateDisplay: '16 July 2026',
            isTaxDeductible: true,
            tax80gEligible: true,
            tax80gCertificateNumber: '80G/2026/AAATS1234R/AB12CD',
            tax80gRegistrationNumber: 'AAATS1234R',
            tax80gNote: null,
            trustName: 'Temple Trust',
            trustAddress: '12 Temple St',
            trustEmail: 'trust@example.com',
            trustPhone: '+91-98765-43210',
            trustPan: 'AAACT1234D',
        );
    }

    public function testViewDataMapsTheTypedDocumentOntoTheDesignContract(): void
    {
        $worker = new DesignWorker($this->app['view'], new InMemoryPdfWrapper('%PDF'));
        $data = $worker->viewData($this->makeDocument());

        self::assertSame('TR-2026-000042-A7c3ZpQ9', $data['receipt_number']);
        self::assertSame('₹5,000.00', $data['amount_display']);
        self::assertSame('FY 2026-27', $data['fy_label']);
        self::assertSame('ABCTY1234D', $data['donor_pan']);
        self::assertSame('80G/2026/AAATS1234R/AB12CD', $data['tax_80g_certificate_number']);
        self::assertSame('AAATS1234R', $data['tax_80g_registration_number']);
        self::assertSame('16 July 2026', $data['issued_date']);
    }

    public function testCompileRendersTheRealDesignTemplate(): void
    {
        $pdf = new InMemoryPdfWrapper('%PDF-1.4 designed %%EOF');
        $worker = new DesignWorker($this->app['view'], $pdf);

        $result = $worker->compile($this->makeDocument());

        self::assertTrue($result->isOk(), (string) $result->error());
        self::assertSame('%PDF-1.4 designed %%EOF', $result->value());
    }

    public function testRenderedDesignContainsTheCanonicalSnapshot(): void
    {
        $worker = new DesignWorker($this->app['view'], new InMemoryPdfWrapper('%PDF'));
        $html = $worker->renderHtml($this->makeDocument());

        // The document and the design agree on every material fact.
        self::assertStringContainsString('TR-2026-000042-A7c3ZpQ9', $html);
        self::assertStringContainsString('₹5,000.00', $html);
        self::assertStringContainsString('Five Thousand Rupees Only', $html);
        self::assertStringContainsString('Priya Sharma', $html);
        self::assertStringContainsString('AAACT1234D', $html);  // donee PAN
        self::assertStringContainsString('ABCTY1234D', $html);   // donor PAN
        self::assertStringContainsString('80G/2026/AAATS1234R/AB12CD', $html);
        self::assertStringContainsString('AAATS1234R', $html);   // registration ≠ certificate
        self::assertStringContainsString('16 July 2026', $html);
        self::assertStringContainsString('12 Temple Lane', $html);
    }

    public function testDesignStylesheetIsATokenisedStyleLayer(): void
    {
        $styles = $this->app['view']->make('receipts.design.styles')->render();

        self::assertStringContainsString('--receipt-accent', $styles);
        self::assertStringContainsString('<style>', $styles);
    }
}
