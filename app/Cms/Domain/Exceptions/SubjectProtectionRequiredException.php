<?php

declare(strict_types=1);

namespace App\Cms\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown by the media-generation pipeline when a source image's subjects
 * are identity-critical (human faces or a venerated idol).
 *
 * This is the preemptive authenticity guard: the Flux2 diffusion model must
 * NEVER re-render faces/idols because it can gloss skin, redraw eyes, or
 * otherwise strip the person's real identity. When this exception escapes,
 * the caller must route the image through the structure-preserving upscale
 * path instead of the diffusion re-render.
 */
final class SubjectProtectionRequiredException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $sourceName,
        private readonly string $classification,
    ) {
        parent::__construct($message);
    }

    public static function forSource(string $sourceName, string $classification): self
    {
        return new self(
            sprintf(
                'Subject authenticity guard: "%s" classified as %s — refusing Flux2 re-render. '
                . 'Use the structure-preserving upscale path to keep identity intact.',
                $sourceName,
                $classification,
            ),
            sourceName: $sourceName,
            classification: $classification,
        );
    }

    public function sourceName(): string
    {
        return $this->sourceName;
    }

    public function classification(): string
    {
        return $this->classification;
    }

    public function errorCode(): string
    {
        return 'cms.media_subject_protection_required';
    }

    public function context(): array
    {
        return [
            'source_name' => $this->sourceName,
            'classification' => $this->classification,
            'required_action' => 'structure-preserving-upscale',
        ];
    }
}