<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);

use App\Cms\Domain\ValueObjects\Blocks\Block;
use App\Cms\Domain\ValueObjects\Blocks\CtaButtonBlock;
use App\Cms\Domain\ValueObjects\Blocks\DividerBlock;
use App\Cms\Domain\ValueObjects\Blocks\HeadingBlock;
use App\Cms\Domain\ValueObjects\Blocks\ImageBlock;
use App\Cms\Domain\ValueObjects\Blocks\ParagraphBlock;
use App\Cms\Infrastructure\Rendering\BlockRendererRegistry;
use App\Persistence\ValueObjects\EntityId;

/** @var BlockRendererRegistry $registry */
$registry = app(BlockRendererRegistry::class);

$fixtures = [
    'paragraph' => new ParagraphBlock(text: 'Hello from paragraph'),
    'heading'   => new HeadingBlock(level: 2, text: 'Section title'),
    'image'     => new ImageBlock(
        fileId: EntityId::fromString('file_01HFAKE'),
        alt: 'A sample image',
        caption: null,
        width: null,
        height: null,
    ),
    'cta'       => new CtaButtonBlock(
        label: 'Donate',
        url: 'https://example.test/donate',
        style: CtaButtonBlock::STYLE_PRIMARY,
        openInNewTab: false,
    ),
    'divider'   => new DividerBlock(
        style: DividerBlock::STYLE_SOLID,
        width: DividerBlock::WIDTH_FULL,
    ),
];

/** @var \App\Cms\Infrastructure\UrlResolution\StubImageUrlResolver $resolver */
$resolver = app(\App\Cms\Contracts\ImageUrlResolverContract::class);

// Per Probe-renderer seam: the ImageBlockRenderer takes the URL resolver
// in its constructor (see CmsServiceProvider line 106). The registry
// already builds the renderer with the bound resolver, so we just render.

$checks = [];
foreach ($fixtures as $name => $block) {
    try {
        $html = $registry->render($block);
        // Empty HTML would be a bug; assert at least some non-empty output.
        $checks[] = [
            'name'   => "Block '{$name}' renders",
            'passed' => is_string($html) && $html !== '',
        ];
    } catch (\Throwable $e) {
        $checks[] = [
            'name'   => "Block '{$name}' renders",
            'passed' => false,
        ];
    }
}

cms_respond('cms05', $checks, [
    'block_types_rendered' => array_keys($fixtures),
], (int) ($start * 1000));

function cms_respond(string $probe, array $checks, array $evidence, int $startMs): never
{
    $passed = array_reduce($checks, fn ($c, $i) => $c && $i['passed'], true);
    $out = [
        'probe'      => $probe,
        'status'     => $passed ? 'pass' : 'fail',
        'durationMs' => (int) ((microtime(true) * 1000) - $startMs),
        'checks'     => $checks,
        'evidence'   => $evidence,
    ];
    echo json_encode($out, JSON_UNESCAPED_SLASHES) . "\n";
    exit($passed ? 0 : 1);
}
