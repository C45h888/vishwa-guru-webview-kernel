<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The mission-quote block of `static_pages.homepage_content`.
 *
 * Eyebrow and quote are required. Attribution is optional and may be
 * null when the trust wishes to leave the quote unattributed.
 */
final readonly class HomepageMissionQuote
{
    public function __construct(
        private string $eyebrow,
        private string $quote,
        private ?string $attribution,
    ) {
        if (trim($eyebrow) === '') {
            throw new InvalidArgumentException('HomepageMissionQuote eyebrow cannot be empty');
        }
        if (trim($quote) === '') {
            throw new InvalidArgumentException('HomepageMissionQuote quote cannot be empty');
        }
        if ($attribution !== null && trim($attribution) === '') {
            throw new InvalidArgumentException(
                'HomepageMissionQuote attribution must be null or non-empty'
            );
        }
    }

    public function eyebrow(): string
    {
        return $this->eyebrow;
    }

    public function quote(): string
    {
        return $this->quote;
    }

    public function attribution(): ?string
    {
        return $this->attribution;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eyebrow' => $this->eyebrow,
            'quote' => $this->quote,
            'attribution' => $this->attribution,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            eyebrow: (string) ($row['eyebrow'] ?? ''),
            quote: (string) ($row['quote'] ?? ''),
            attribution: isset($row['attribution']) ? (string) $row['attribution'] : null,
        );
    }
}
