<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A single statistic on the About page (V2+).
 *
 * The number is rendered large and serif as the type anchor of the
 * stats grid. Label is the short heading. Description (optional) gives
 * the reader a single sentence of context for why the number matters.
 */
final readonly class AboutStat
{
    public function __construct(
        private string $number,
        private string $label,
        private ?string $description = null,
    ) {
        if (trim($number) === '') {
            throw new InvalidArgumentException('AboutStat number cannot be empty');
        }
        if (trim($label) === '') {
            throw new InvalidArgumentException('AboutStat label cannot be empty');
        }
        if ($description !== null && trim($description) === '') {
            throw new InvalidArgumentException('AboutStat description must be null or non-empty');
        }
        // Soft cap so a malformed payload doesn't paint a banner-wide "9999999+".
        if (mb_strlen($number) > 16) {
            throw new InvalidArgumentException(
                'AboutStat number must be 16 characters or fewer (got '.mb_strlen($number).')'
            );
        }
    }

    public function number(): string
    {
        return $this->number;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'label' => $this->label,
            'description' => $this->description,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            number: (string) ($row['number'] ?? ''),
            label: (string) ($row['label'] ?? ''),
            description: isset($row['description']) && $row['description'] !== ''
                ? (string) $row['description']
                : null,
        );
    }
}