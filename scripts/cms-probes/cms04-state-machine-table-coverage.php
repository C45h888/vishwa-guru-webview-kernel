<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);

$machine = app(\App\Cms\Domain\StateMachines\StaticPageStateMachine::class);

$states = [
    \App\Cms\Domain\Enums\StaticPageState::DRAFT,
    \App\Cms\Domain\Enums\StaticPageState::PUBLISHED,
    \App\Cms\Domain\Enums\StaticPageState::UPDATED,
    \App\Cms\Domain\Enums\StaticPageState::ARCHIVED,
];

$events = [
    \App\Cms\Domain\Enums\CmsTransitionEvent::PAGE_PUBLISHED,
    \App\Cms\Domain\Enums\CmsTransitionEvent::PAGE_EDITED,
    \App\Cms\Domain\Enums\CmsTransitionEvent::PAGE_ARCHIVED,
    \App\Cms\Domain\Enums\CmsTransitionEvent::PAGE_RESTORED,
];

// Expected matrix from cms-architecture.md §3.4.1 — see also
// StaticPageStateMachineTest::transitionMatrix for the unit equivalent.
$expectations = [
    'draft'     => ['page_published' => true,  'page_edited' => false, 'page_archived' => true,  'page_restored' => false],
    'published' => ['page_published' => false, 'page_edited' => true,  'page_archived' => true,  'page_restored' => false],
    'updated'   => ['page_published' => true,  'page_edited' => false, 'page_archived' => true,  'page_restored' => false],
    'archived'  => ['page_published' => false, 'page_edited' => false, 'page_archived' => false, 'page_restored' => true],
];

$checks = [];
$mismatches = [];
foreach ($states as $from) {
    foreach ($events as $event) {
        $expected = $expectations[$from->value][$event->value];
        $actual = $machine->canTransition($from, $event);
        $name = "{$from->value} + {$event->value}";
        $checks[] = [
            'name'   => $name,
            'passed' => $expected === $actual,
        ];
        if ($expected !== $actual) {
            $mismatches[] = $name.' expected='.var_export($expected, true).' actual='.var_export($actual, true);
        }
    }
}

cms_respond('cms04', $checks, [
    'cells_checked' => count($checks),
    'mismatches'    => $mismatches,
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
