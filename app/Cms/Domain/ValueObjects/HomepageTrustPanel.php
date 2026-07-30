<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The trust / authority panel of `static_pages.homepage_content`.
 *
 * Carries registration details, tax status, and the trust's operating
 * principles + vows. At least one operating principle and one vow are
 * required — an authority panel that ships empty prose would defeat its
 * purpose.
 */
final readonly class HomepageTrustPanel
{
    /**
     * @param  list<string>  $operatingPrinciples
     * @param  list<string>  $vows
     */
    public function __construct(
        private string $eyebrow,
        private string $title,
        private string $registration,
        private string $taxStatus,
        private array $operatingPrinciples,
        private array $vows,
    ) {
        if (trim($eyebrow) === '') {
            throw new InvalidArgumentException('HomepageTrustPanel eyebrow cannot be empty');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('HomepageTrustPanel title cannot be empty');
        }
        if (trim($registration) === '') {
            throw new InvalidArgumentException('HomepageTrustPanel registration cannot be empty');
        }
        if (trim($taxStatus) === '') {
            throw new InvalidArgumentException('HomepageTrustPanel tax_status cannot be empty');
        }

        $this->assertStringList(
            $operatingPrinciples,
            'operating_principles',
        );
        $this->assertStringList(
            $vows,
            'vows',
        );

        if ($operatingPrinciples === []) {
            throw new InvalidArgumentException(
                'HomepageTrustPanel operating_principles must contain at least one entry'
            );
        }
        if ($vows === []) {
            throw new InvalidArgumentException(
                'HomepageTrustPanel vows must contain at least one entry'
            );
        }
    }

    /**
     * @param  list<string>  $list
     */
    private function assertStringList(array $list, string $field): void
    {
        foreach ($list as $i => $entry) {
            if (! is_string($entry) || trim($entry) === '') {
                throw new InvalidArgumentException(
                    "HomepageTrustPanel {$field} entry at index {$i} must be a non-empty string"
                );
            }
        }
    }

    public function eyebrow(): string
    {
        return $this->eyebrow;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function registration(): string
    {
        return $this->registration;
    }

    public function taxStatus(): string
    {
        return $this->taxStatus;
    }

    /**
     * @return list<string>
     */
    public function operatingPrinciples(): array
    {
        return $this->operatingPrinciples;
    }

    /**
     * @return list<string>
     */
    public function vows(): array
    {
        return $this->vows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'registration' => $this->registration,
            'tax_status' => $this->taxStatus,
            'operating_principles' => $this->operatingPrinciples,
            'vows' => $this->vows,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $operatingPrinciples = self::stringList($row['operating_principles'] ?? []);
        $vows = self::stringList($row['vows'] ?? []);

        return new self(
            eyebrow: (string) ($row['eyebrow'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            registration: (string) ($row['registration'] ?? ''),
            taxStatus: (string) ($row['tax_status'] ?? ''),
            operatingPrinciples: $operatingPrinciples,
            vows: $vows,
        );
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('HomepageTrustPanel list field must be an array');
        }

        $list = [];
        foreach ($value as $i => $entry) {
            if (! is_string($entry) || trim($entry) === '') {
                throw new InvalidArgumentException(
                    "HomepageTrustPanel list entry at index {$i} must be a non-empty string"
                );
            }
            $list[] = $entry;
        }

        return $list;
    }
}
