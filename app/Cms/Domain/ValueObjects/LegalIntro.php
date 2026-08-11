<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

/**
 * The intro section of the Legal page.
 *
 * A small typed VO that pairs an eyebrow / title / body triple with the
 * public copy that opens the legal regulatory-standing page. The values
 * are surfaced verbatim to the Svelte layer (`LegalIntroProps`).
 */
final class LegalIntro
{
    public function __construct(
        private readonly string $eyebrow,
        private readonly string $title,
        private readonly string $body,
    ) {
        if (trim($this->eyebrow) === '') {
            throw new \InvalidArgumentException('LegalIntro eyebrow cannot be empty');
        }
        if (trim($this->title) === '') {
            throw new \InvalidArgumentException('LegalIntro title cannot be empty');
        }
        if (trim($this->body) === '') {
            throw new \InvalidArgumentException('LegalIntro body cannot be empty');
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

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $eyebrow = isset($row['eyebrow']) && is_string($row['eyebrow'])
            ? $row['eyebrow']
            : '';
        $title = isset($row['title']) && is_string($row['title'])
            ? $row['title']
            : '';
        $body = isset($row['body']) && is_string($row['body'])
            ? $row['body']
            : '';

        return new self(
            eyebrow: $eyebrow,
            title: $title,
            body: $body,
        );
    }
}
