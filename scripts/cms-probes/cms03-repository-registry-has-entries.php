<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);

/** @var \App\Persistence\Contracts\RepositoryRegistryContract $registry */
$registry = app(\App\Persistence\Contracts\RepositoryRegistryContract::class);

$entityTypes = [
    \App\Cms\Domain\Entities\StaticPage::ENTITY_TYPE,
    \App\Cms\Domain\Entities\HeroBanner::ENTITY_TYPE,
    \App\Cms\Domain\Entities\StaticPageReference::ENTITY_TYPE,
    \App\Cms\Domain\Entities\ContactInformation::ENTITY_TYPE,
];

$checks = [];
foreach ($entityTypes as $type) {
    $checks[] = [
        'name'   => "Registry has entry for '{$type}'",
        'passed' => $registry->has($type),
    ];
}

cms_respond('cms03', $checks, [
    'entity_types_checked' => $entityTypes,
    'total_registered'     => count($registry->entityTypes()),
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
