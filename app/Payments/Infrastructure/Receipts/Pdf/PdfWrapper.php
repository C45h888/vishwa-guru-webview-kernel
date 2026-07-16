<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts\Pdf;

/**
 * Abstraction over PDF rendering engines.
 *
 * All concrete implementations must be placed in this directory.
 * The ONLY file in app/Payments/ allowed to reference barryvdh/laravel-dompdf
 * is DomPdfWrapper.
 */
interface PdfWrapper
{
    /**
     * Render HTML to PDF bytes.
     *
     * @param  string  $viewHtml  Full HTML document (DOCTYPE + html + body)
     * @return string  Raw PDF bytes
     */
    public function render(string $viewHtml): string;

    /**
     * Write raw content bytes to a disk path.
     *
     * @param  string  $content  Raw bytes
     * @param  string  $disk     Storage disk name (e.g. 'local', 's3')
     * @param  string  $path     Path within the disk
     */
    public function writeToDisk(string $content, string $disk, string $path): void;

    /**
     * Check whether a file exists at the given disk+path.
     */
    public function exists(string $disk, string $path): bool;

    /**
     * Return the file size in bytes of a file at the given disk+path.
     *
     * @return int  bytes; 0 if file does not exist
     */
    public function size(string $disk, string $path): int;
}
