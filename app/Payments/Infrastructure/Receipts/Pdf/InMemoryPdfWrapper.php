<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts\Pdf;

/**
 * In-memory test double for PdfWrapper.
 *
 * Mimics DomPdfWrapper's interface contract without any I/O.
 * Stores rendered bytes in a static map keyed by disk+path for
 * later assertion in tests.
 */
final class InMemoryPdfWrapper implements PdfWrapper
{
    /**
     * @var array<string, string>  keyed as "{$disk}:{$path}" => $bytes
     */
    private array $storage = [];

    /**
     * @var list<string>  queued bytes; each render() / writeToDisk() call
     *                       shifts one entry off this queue
     */
    private array $renderQueue = [];

    public function __construct(
        private string $renderedBytes = "%PDF-1.4\nmock-receipt-content\n%%EOF",
    ) {}

    /**
     * @param  string  $viewHtml
     * @return string
     */
    public function render(string $viewHtml): string
    {
        if (! empty($this->renderQueue)) {
            return array_shift($this->renderQueue);
        }

        return $this->renderedBytes;
    }

    /**
     * @param  string  $content
     * @param  string  $disk
     * @param  string  $path
     */
    public function writeToDisk(string $content, string $disk, string $path): void
    {
        if (! empty($this->renderQueue)) {
            $content = array_shift($this->renderQueue);
        }

        $key = "{$disk}:{$path}";
        $this->storage[$key] = $content;
    }

    /**
     * @param  string  $disk
     * @param  string  $path
     */
    public function exists(string $disk, string $path): bool
    {
        return isset($this->storage["{$disk}:{$path}"]);
    }

    /**
     * @param  string  $disk
     * @param  string  $path
     * @return int
     */
    public function size(string $disk, string $path): int
    {
        return isset($this->storage["{$disk}:{$path}"])
            ? strlen($this->storage["{$disk}:{$path}"])
            : 0;
    }

    /**
     * Retrieve stored bytes for test assertions.
     */
    public function getStored(string $disk, string $path): ?string
    {
        return $this->storage["{$disk}:{$path}"] ?? null;
    }

    /**
     * Pre-load bytes to be returned by the next N render/write calls.
     *
     * @param  list<string>  $bytesList
     */
    public function queueRenderedBytes(array $bytesList): void
    {
        $this->renderQueue = array_merge($this->renderQueue, $bytesList);
    }

    /**
     * Reset all stored content.
     */
    public function reset(): void
    {
        $this->storage = [];
        $this->renderQueue = [];
    }
}
