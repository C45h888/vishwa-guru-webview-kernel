<?php

declare(strict_types=1);

namespace Tests\Unit\Cms\Content;

use App\Cms\Content\TermsPageDefinition;
use App\Cms\Domain\ValueObjects\PageBody;
use PHPUnit\Framework\TestCase;

final class TermsPageDefinitionTest extends TestCase
{
    public function test_definition_is_a_valid_cms_page_with_approved_donation_semantics(): void
    {
        self::assertSame('terms', TermsPageDefinition::SLUG);
        self::assertSame('Terms & Conditions', TermsPageDefinition::TITLE);

        $body = PageBody::fromArray([
            'version' => PageBody::CURRENT_VERSION,
            'blocks' => TermsPageDefinition::bodyBlocks(),
        ]);

        self::assertNotEmpty($body->blocks());

        $copy = implode("\n", array_map(
            static fn (array $block): string => $block['text'],
            TermsPageDefinition::bodyBlocks(),
        ));

        self::assertStringContainsString('children in the Trust’s care', $copy);
        self::assertStringContainsString('If contributions exceed the target', $copy);
        self::assertStringContainsString('closes without reaching its target', $copy);
        self::assertStringContainsString('handle any applicable refund manually', $copy);
        self::assertStringContainsString('Indian rupees', $copy);
    }
}
