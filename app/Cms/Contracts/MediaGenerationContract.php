<?php

declare(strict_types=1);

namespace App\Cms\Contracts;

/**
 * MediaGenerationContract — the public surface for generating/re-rendering
 * Cms media through the published Flux2 pipeline on the InvokeAI pod.
 *
 * Business rules (constitution doctrine):
 *   - The Cms kernel owns the *media surface*; generation results land in
 *     the same file_assets / cms_media_assets storage that /media/{id}
 *     serves. Controllers/commands depend on THIS contract, never on the
 *     InvokeAI HTTP client directly.
 *   - Subject authenticity is a hard constraint: images whose subjects are
 *     human/faces must NOT be re-rendered through the diffusion model
 *     (the model can gloss/alter identity). The pipeline branches on a
 *     SubjectAuthenticityClassifier result and returns a preserved path
 *     when faces are present.
 *
 * Implementations:
 *   - \App\Cms\Services\Flux2ImageGenerationService (HTTP → InvokeAI queue API)
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/config/media_generation.php
 */
interface MediaGenerationContract
{
    /**
     * Re-render a source image with the Flux2 pipeline, preserving subject
     * identity 1:1 while recomposing angle/lighting into a wide 16:9
     * photographic-landscape output.
     *
     * @param  string  $sourceImageBytes  raw bytes of the source image
     * @param  string  $instruction       subject/angle/lighting instruction (injected into the prompt template)
     * @return array<string, mixed>       { cmsMediaAssetId, fileAssetId, width, height, bytes }
     *
     * @throws \App\Cms\Domain\Exceptions\SubjectProtectionRequiredException when faces are detected (must use the
     *         structure-preserving path instead).
     */
    public function rerenderWideShot(string $sourceImageBytes, string $instruction, string $subjectSort): array;
}