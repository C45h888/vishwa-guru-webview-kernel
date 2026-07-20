<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Shared\ValueObjects\AbstractValueObject;
use InvalidArgumentException;

/**
 * A URL-safe slug identifying a Static Page.
 *
 * Constraints:
 *   - lowercase ASCII letters, digits, hyphens
 *   - must start with a letter
 *   - max 80 chars
 *   - regex: /^[a-z][a-z0-9-]{0,79}$/
 *
 * Equality is by string value. The slug is part of the website
 * structure (per domain-modules.md:386-391) — slugs are admin-free
 * in V1 but uniqueness is enforced by the kernel via the partial
 * unique index `static_pages_slug_live_idx`.
 */
final class PageSlug extends AbstractValueObject
{
    private const PATTERN = '/^[a-z][a-z0-9-]{0,79}$/';

    public function __construct(
        private readonly string $value,
    ) {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                "Invalid PageSlug: {$value} (must match /^[a-z][a-z0-9-]{0,79}$/)"
            );
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}