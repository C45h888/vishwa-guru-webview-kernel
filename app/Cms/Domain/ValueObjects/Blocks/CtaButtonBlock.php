<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects\Blocks;

use InvalidArgumentException;

/**
 * A call-to-action button block — rendered as `<a>` with a style class.
 *
 * URL must parse as a valid URL. `openInNewTab` triggers
 * `target="_blank" rel="noopener noreferrer"`.
 */
final readonly class CtaButtonBlock implements Block
{
    public const STYLE_PRIMARY = 'primary';
    public const STYLE_SECONDARY = 'secondary';
    public const STYLE_GHOST = 'ghost';

    private const STYLES = [
        self::STYLE_PRIMARY,
        self::STYLE_SECONDARY,
        self::STYLE_GHOST,
    ];

    public function __construct(
        public string $label,
        public string $url,
        public string $style = self::STYLE_PRIMARY,
        public bool $openInNewTab = false,
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException('CtaButtonBlock label cannot be empty');
        }
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("CtaButtonBlock url is not a valid URL: {$url}");
        }
        if (! in_array($style, self::STYLES, true)) {
            throw new InvalidArgumentException(
                "CtaButtonBlock style must be one of [primary, secondary, ghost] (got {$style})"
            );
        }
    }

    public function type(): string
    {
        return 'cta_button';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'label' => $this->label,
            'url' => $this->url,
            'style' => $this->style,
            'open_in_new_tab' => $this->openInNewTab,
        ];
    }
}