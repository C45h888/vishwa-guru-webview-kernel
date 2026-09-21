<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Result;
use Illuminate\Contracts\View\Factory as ViewFactory;
use RuntimeException;

/**
 * Renders a Receipt entity to PDF bytes via a Blade template.
 *
 * Flow:
 *   1. Format entity data via ReceiptFormatter
 *   2. Render Blade template → HTML
 *   3. Pass HTML to PdfWrapper → bytes
 *
 * No I/O beyond the PdfWrapper (which handles storage). No direct Storage:: calls.
 */
class ReceiptPdfGenerator
{
    public function __construct(
        private readonly PdfWrapper $pdf,
        private readonly ReceiptFormatter $formatter,
        private readonly ViewFactory $views,
        private readonly ConfigurationContract $config,
    ) {}

    /**
     * Render a receipt to PDF bytes.
     *
     * @return Result<string> PDF bytes on success
     */
    public function render(Receipt $receipt, Payment $payment, Donation $donation): Result
    {
        try {
            // Build trust info from config
            $trustInfo = [
                'name' => (string) $this->config->get('receipts.branding.trust_name', 'Temple Trust'),
                'address' => (string) $this->config->get('receipts.branding.trust_address', ''),
                'email' => (string) $this->config->get('receipts.branding.trust_email', ''),
                'phone' => (string) $this->config->get('receipts.branding.trust_phone', ''),
                'pan' => (string) $this->config->get('receipts.80g.trust_pan', ''),
            ];

            // Format data for the template
            $data = $this->formatter->format($receipt, $payment, $donation, $trustInfo);

            // Build the full HTML document
            $html = $this->buildHtmlDocument($data);

            // Render to PDF bytes
            $bytes = $this->pdf->render($html);

            if ($bytes === '' || $bytes === false) {
                return Result::failure('ReceiptPdfGenerator: PdfWrapper returned empty output');
            }

            return Result::success($bytes);
        } catch (RuntimeException $e) {
            return Result::failure("ReceiptPdfGenerator: rendering failed — {$e->getMessage()}");
        } catch (\Throwable $e) {
            return Result::failure("ReceiptPdfGenerator: unexpected error — {$e->getMessage()}");
        }
    }

    /**
     * Build the full HTML document shell around receipt data.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildHtmlDocument(array $data): string
    {
        $view = $this->views->make('receipts.receipt', $data);

        $doctype = '<!DOCTYPE html>';
        $lang = '<html lang="en">';
        $head = <<<HTML
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {$data['receipt_number']}</title>
    <style>
        /* Embedded print-friendly styles */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
        .receipt-page { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #8B0000; padding-bottom: 12px; margin-bottom: 20px; }
        .trust-name { font-size: 20px; font-weight: bold; color: #8B0000; }
        .receipt-title { font-size: 16px; margin-top: 6px; letter-spacing: 2px; text-transform: uppercase; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 16px 0; }
        .field-label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .field-value { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .amount-box { background: #f9f9f9; border: 1px solid #ddd; padding: 12px; text-align: center; margin: 16px 0; }
        .amount-value { font-size: 24px; font-weight: bold; color: #8B0000; }
        .amount-words { font-size: 14px; color: #444; margin-top: 4px; font-style: italic; }
        .section { margin: 16px 0; }
        .section-title { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #8B0000; border-bottom: 1px solid #8B0000; padding-bottom: 4px; margin-bottom: 8px; }
        .address-block { font-size: 12px; line-height: 1.6; white-space: pre-line; }
        .tax-cert-box { background: #fffbea; border: 1px solid #e6c200; padding: 10px; margin-top: 12px; font-size: 11px; }
        .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #ccc; font-size: 10px; color: #888; text-align: center; }
        @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
HTML;

        $bodyContent = $view->render();

        return <<<HTML
{$doctype}
{$lang}
{$head}
<body>
{$bodyContent}
</body>
</html>
HTML;
    }
}
