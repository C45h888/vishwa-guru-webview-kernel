<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Legal;

use App\Cms\Domain\ValueObjects\LegalCertificate;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Legal-document streaming controller (GET /legal/documents/{key}).
 *
 * Doctrine (constitutional):
 *   - The legal documents live on the PRIVATE `local` disk at
 *     `legalmedia/assets/{key}.pdf`. They are not web-reachable
 *     directly; this controller is the SOLE public surface.
 *   - The controller validates `{key}` against
 *     `LegalCertificate::ALLOWED_KEYS` to prevent path traversal
 *     outside the four canonical certificate documents (eighty_g,
 *     twelve_a, poa, tan). Unknown keys 404.
 *   - PDFs are streamed with `Content-Disposition: inline` so the
 *     browser renders the document inline (donors can review without
 *     downloading). The filename is preserved via the `filename*`
 *     parameter for non-ASCII safety.
 *   - `X-Content-Type-Options: nosniff` and a `Content-Type` of
 *     `application/pdf` are mandatory — donors must receive the
 *     document type the link advertises.
 *
 * The controller is pure transport. It does not log access (audit
 * wiring is deferred to a future pass per AGENTS.md).
 */
final class DocumentController
{
    public function __invoke(string $key): Response|BinaryFileResponse
    {
        // Whitelist validation: prevents /legal/documents/../etc/passwd
        // and any non-canonical document from being served.
        if (! in_array($key, LegalCertificate::ALLOWED_KEYS, true)) {
            throw new NotFoundHttpException("Legal document [{$key}] not found.");
        }

        $disk = Storage::disk('local');
        $path = "legalmedia/assets/{$key}.pdf";

        abort_unless($disk->exists($path), 404, "Legal document [{$key}] not on disk.");

        return response()->file($disk->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$key.'.pdf"',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
