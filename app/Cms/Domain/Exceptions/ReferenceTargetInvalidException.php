<?php

declare(strict_types=1);

namespace App\Cms\Domain\Exceptions;

use App\Cms\Domain\Enums\PageReferenceType;
use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a static page reference targets an entity that does not
 * exist in the producing kernel, or the reference type itself is not
 * resolvable in V1 (e.g. gallery_image, event — those kernels are
 * deferred).
 *
 * In V1 the ReferenceResolutionService does NOT throw this exception
 * — it returns a `ResolvedReference` with `status = Unresolved` so the
 * page can render with a gap. This exception is reserved for V2 when
 * admin tooling surfaces broken references as actionable items.
 */
final class ReferenceTargetInvalidException extends DomainException
{
    public function __construct(
        string $message,
        private readonly PageReferenceType $referenceType,
        private readonly string $referenceId,
    ) {
        parent::__construct($message);
    }

    public static function forReference(
        PageReferenceType $type,
        string $referenceId,
        string $reason,
    ): self {
        return new self(
            sprintf(
                'Invalid reference target: %s id=%s (%s)',
                $type->value,
                $referenceId,
                $reason,
            ),
            referenceType: $type,
            referenceId: $referenceId,
        );
    }

    public function errorCode(): string
    {
        return 'cms.reference.target.invalid';
    }

    public function context(): array
    {
        return [
            'reference_type' => $this->referenceType->value,
            'reference_id' => $this->referenceId,
        ];
    }
}