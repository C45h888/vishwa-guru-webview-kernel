<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * DomPDF implementation of PdfWrapper.
 *
 * This is the ONLY file in app/Payments/ that may reference
 * \Barryvdh\DomPDF\ or the PDF facade. All other receipt infrastructure
 * code depends only on the PdfWrapper interface.
 *
 * Non-final so unit tests can substitute a recording stub via inheritance;
 * production code resolves through DI and never sees a subclass.
 */
class DomPdfWrapper implements PdfWrapper
{
    /**
     * Render HTML to PDF bytes using barryvdh/laravel-dompdf.
     *
     * @param  string  $viewHtml  Full HTML document
     * @return string  Raw PDF bytes
     */
    public function render(string $viewHtml): string
    {
        // Canonical barryvdh/laravel-dompdf usage: go through the facade's
        // static API. The previous code called `getDomPDF()` on an instance
        // resolved via `app(Pdf::class)`, which returns the Facade class —
        // not the underlying 'dompdf.wrapper' service — and fatally failed
        // with "Call to undefined method ...\Facade\Pdf::getDomPDF()".
        $pdf = Pdf::loadHTML($viewHtml)->setPaper('a4', 'portrait');

        $output = $pdf->output();

        if ($output === null || $output === '') {
            throw new RuntimeException('DomPdfWrapper: PDF rendering produced empty output');
        }

        return $output;
    }

    /**
     * Write bytes to a Laravel storage disk.
     *
     * @param  string  $content  Raw bytes
     * @param  string  $disk    Storage disk name
     * @param  string  $path    Path within the disk
     */
    public function writeToDisk(string $content, string $disk, string $path): void
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($disk);

        $written = $disk->put($path, $content);

        if (! $written) {
            throw new RuntimeException("DomPdfWrapper: failed to write PDF to disk [{$disk}]/{$path}");
        }
    }

    /**
     * @param  string  $disk
     * @param  string  $path
     */
    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }

    /**
     * @param  string  $disk
     * @param  string  $path
     * @return int
     */
    public function size(string $disk, string $path): int
    {
        /** @var FilesystemAdapter $diskAdapter */
        $diskAdapter = Storage::disk($disk);

        if (! $diskAdapter->exists($path)) {
            return 0;
        }

        return (int) $diskAdapter->size($path);
    }
}
