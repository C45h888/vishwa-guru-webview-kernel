<?php

declare(strict_types=1);

namespace App\Cms\Services;

use DateTimeImmutable;
use RuntimeException;

/**
 * SubjectAuthenticityClassifier — preemptive guard that decides whether a
 * source image is safe to re-render through the Flux2 diffusion model.
 *
 * Constraint (user directive): never re-render images whose subjects are
 * human faces — the diffusion model can gloss the skin, draw the eyes
 * differently, or otherwise strip the person's real identity, producing
 * an inauthentic result. For architecture-only subjects (halls, pillars,
 * mandapas) re-rendering is safe because there is no face to lose.
 *
 * Classification tiers:
 *   - PRESERVE_FACE  → contains human faces / a person as the subject;
 *                      must use the structure-preserving upscale path and
 *                      must NOT run through the Flux2 model.
 *   - PRESERVE_IDOL  → a venerated idol (e.g. a deity murti). Faces on an
 *                      idol are likewise identity-critical; preserved.
 *   - ALLOW_GENERATE → architecture-only (no face). Safe for Flux2 re-render.
 *
 * The classifier is a dependency-injected strategy so callers can pass a
 * real CV model-backed detector in future; the default implementation is
 * a conservative filename/caption heuristic plus an explicit allow-list.
 *
 * Doctrine: the classifier is pure business logic in the service layer
 * (no persistence, no HTTP). It never throws for a normal classification;
 * it returns the safest tier when the signal is ambiguous.
 */
final class SubjectAuthenticityClassifier
{
    // A face/identity-critical filename fragment is a strong signal.
    private const FACE_IDENTITY_HINTS = [
        'ganesha', 'idol', 'murti', 'priest', 'children', 'people',
        'human', 'portrait', 'face', 'devotee', 'worshipper', 'family',
        'banyan', 'man', 'woman', 'child', 'deity',
    ];

    // An explicit allow-list of known architecture-only sources that are
    // safe to re-render regardless of filename hints.
    private const ARCHITECTURE_ALLOWLIST = [
        'pillar-hall', 'mandapa-hall', 'sanctuary-hall', 'pillar', 'hall',
        'mandapa', 'sanctuary', 'interior', 'architecture',
    ];

    /**
     * Classify a source image by its identity-critical signal; fell back
     * to the source filename for the default heuristic.
     *
     * @param  string      $sourceName   best available source label (e.g. original filename)
     * @param  string|null $caption      optional caption present on the record
     * @return string                   one of: preserve_face | preserve_idol | allow_generate
     */
    public function classify(string $sourceName, ?string $caption = null): string
    {
        $signal = strtolower(implode(' ', [$caption ?? '', $sourceName]));

        // 1) Explicit architecture allow-list wins (recognised to have no faces).
        foreach (self::ARCHITECTURE_ALLOWLIST as $fragment) {
            if (str_contains($signal, $fragment)) {
                return 'allow_generate';
            }
        }

        // 2) Idol/deity is identity-critical → preserve.
        foreach (['idol', 'murti', 'deity', 'ganesha'] as $fragment) {
            if (str_contains($signal, $fragment)) {
                return 'preserve_idol';
            }
        }

        // 3) Face/person hints → preserve.
        foreach (self::FACE_IDENTITY_HINTS as $fragment) {
            if (str_contains($signal, $fragment)) {
                return 'preserve_face';
            }
        }

        // 4) Ambiguous → default to the SAFE option (preserve_face). Never
        //    risk re-rendering an unknown subject.
        return 'preserve_face';
    }
}