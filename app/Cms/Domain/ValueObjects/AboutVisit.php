<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The "Plan your visit" section of the About page (V2+).
 *
 * Carries everything a first-time visitor needs: an inviting prose
 * body, the address block (street/city), the timings block (a free-form
 * string the Svelte layer renders as preformatted text), the primary
 * phone number, the dress code (a short paragraph), and an optional
 * map URL the Svelte layer surfaces as a CTA.
 */
final readonly class AboutVisit
{
    public function __construct(
        private string $eyebrow,
        private string $title,
        private string $body,
        private string $address,
        private string $timings,
        private string $phone,
        private string $dressCode,
        private ?string $mapUrl = null,
    ) {
        if (trim($eyebrow) === '') {
            throw new InvalidArgumentException('AboutVisit eyebrow cannot be empty');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('AboutVisit title cannot be empty');
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('AboutVisit body cannot be empty');
        }
        if (trim($address) === '') {
            throw new InvalidArgumentException('AboutVisit address cannot be empty');
        }
        if (trim($timings) === '') {
            throw new InvalidArgumentException('AboutVisit timings cannot be empty');
        }
        if (trim($phone) === '') {
            throw new InvalidArgumentException('AboutVisit phone cannot be empty');
        }
        if (trim($dressCode) === '') {
            throw new InvalidArgumentException('AboutVisit dress_code cannot be empty');
        }
        if ($mapUrl !== null && $mapUrl !== '' && ! str_starts_with($mapUrl, 'http')) {
            throw new InvalidArgumentException(
                'AboutVisit map_url must be null or an absolute http(s) URL'
            );
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

    public function body(): string
    {
        return $this->body;
    }

    public function address(): string
    {
        return $this->address;
    }

    public function timings(): string
    {
        return $this->timings;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function dressCode(): string
    {
        return $this->dressCode;
    }

    public function mapUrl(): ?string
    {
        return $this->mapUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'body' => $this->body,
            'address' => $this->address,
            'timings' => $this->timings,
            'phone' => $this->phone,
            'dress_code' => $this->dressCode,
            'map_url' => $this->mapUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $mapUrl = isset($row['map_url']) && $row['map_url'] !== ''
            ? (string) $row['map_url']
            : null;

        return new self(
            eyebrow: (string) ($row['eyebrow'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            body: (string) ($row['body'] ?? ''),
            address: (string) ($row['address'] ?? ''),
            timings: (string) ($row['timings'] ?? ''),
            phone: (string) ($row['phone'] ?? ''),
            dressCode: (string) ($row['dress_code'] ?? ''),
            mapUrl: $mapUrl,
        );
    }
}