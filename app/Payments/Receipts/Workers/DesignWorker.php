<?php

declare(strict_types=1);

namespace App\Payments\Receipts\Workers;

use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper;
use App\Shared\Support\Result;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * DesignWorker — compiles the designed receipt document.
 *
 * Semantic boundary: DESIGN lives in resources/views/receipts/design/
 * (template + styles); this worker only maps the typed document onto the
 * template's data contract and hands the rendered bytes back. It never
 * loads data, never validates, never decides anything — by the time a
 * document arrives here, every field is already typed and canonical.
 *
 * Compilation runs under WorkerCadence (see ReceiptSubstrate) — dompdf is
 * the slowest and flakiest stage, so it gets the widest budget and the
 * most attempts.
 */
final class DesignWorker
{
    public const TEMPLATE = 'receipts.design.receipt';

    public function __construct(
        private readonly ViewFactory $views,
        private readonly PdfWrapper $pdf,
    ) {
    }

    /**
     * Compile the designed PDF bytes for a typed document.
     *
     * @return Result<string>
     */
    public function compile(ReceiptDocument $document): Result
    {
        try {
            $html = $this->renderHtml($document);
            $bytes = $this->pdf->render($html);

            if ($bytes === '' || $bytes === false) {
                return Result::failure('DesignWorker: PDF rendering produced empty output');
            }

            return Result::success($bytes);
        } catch (\Throwable $e) {
            return Result::failure('DesignWorker: compilation failed — '.$e->getMessage());
        }
    }

    /**
     * Compile the full HTML document (used by tests + print view).
     */
    public function renderHtml(ReceiptDocument $document): string
    {
        $body = $this->views->make(self::TEMPLATE, $this->viewData($document))->render();

        return '<!DOCTYPE html>'."\n".'<html lang="en">'."\n"
            .$this->views->make('receipts.design.styles')->render()
            ."\n<body>\n".$body."\n</body>\n</html>";
    }

    /**
     * The design's data contract: the typed document projected onto the
     * template field names. The design owns these names; nothing outside
     * this worker may shape them.
     *
     * @return array<string, mixed>
     */
    public function viewData(ReceiptDocument $d): array
    {
        return [
            'trust_name' => $d->trustName,
            'trust_address' => $d->trustAddress,
            'trust_email' => $d->trustEmail,
            'trust_phone' => $d->trustPhone,
            'trust_pan' => $d->trustPan,
            'trust_tan' => $d->trustTan,
            'trust_12a_number' => $d->trustTwelveANumber,

            'receipt_number' => $d->receiptNumber,
            'fy_label' => $d->fyLabel,
            'issued_date' => $d->issuedDateDisplay,

            'payment_reference' => $d->paymentReference,
            'payment_date' => $d->paymentDateDisplay,

            'amount_display' => $d->amountDisplay,
            'amount_in_words' => $d->amountInWords,

            'donor_name' => $d->donorName,
            'donor_email' => $d->donorEmail,
            'donor_pan' => $d->donorPan,
            'donor_address' => $d->donorAddressBlock,

            'campaign_title' => $d->campaignTitle,
            'campaign_description' => $d->campaignDescription,

            'is_tax_deductible' => $d->isTaxDeductible,
            'tax_80g_eligible' => $d->tax80gEligible,
            'tax_80g_certificate_number' => $d->tax80gCertificateNumber,
            'tax_80g_registration_number' => $d->tax80gRegistrationNumber,
            'tax_80g_note' => $d->tax80gNote,

            'content_hash' => $d->contentHash,

            'trust_seal_data_uri' => $this->assetDataUri('receipts/Assets/trust-seal.svg'),
            'signature_data_uri' => $this->assetDataUri('receipts/Assets/signature-trustee.png'),
        ];
    }

    /**
     * Embed a design asset as a data URI so dompdf renders it without
     * filesystem/URL access. Missing assets yield null — the template
     * guards each with @isset. SVG + PNG both safe to inline.
     */
    private function assetDataUri(string $viewRelativePath): ?string
    {
        $full = resource_path('views/'.$viewRelativePath);
        if (! is_file($full)) {
            return null;
        }
        $bytes = @file_get_contents($full);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $mime = str_ends_with($full, '.svg') ? 'image/svg+xml' : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
