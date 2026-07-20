<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);

$bindings = [
    \App\Cms\Domain\Repositories\StaticPageRepositoryContract::class          => \App\Cms\Infrastructure\Repositories\EloquentStaticPageRepository::class,
    \App\Cms\Domain\Repositories\HeroBannerRepositoryContract::class          => \App\Cms\Infrastructure\Repositories\EloquentHeroBannerRepository::class,
    \App\Cms\Domain\Repositories\StaticPageReferenceRepositoryContract::class => \App\Cms\Infrastructure\Repositories\EloquentStaticPageReferenceRepository::class,
    \App\Cms\Domain\Repositories\ContactInformationRepositoryContract::class  => \App\Cms\Infrastructure\Repositories\EloquentContactInformationRepository::class,
];

$checks = [];
foreach ($bindings as $contract => $impl) {
    $resolved = app($contract);
    $checks[] = [
        'name'   => "{$contract} → {$impl}",
        'passed' => $resolved instanceof $impl,
    ];
}

cms_respond('cms02', $checks, [
    'binding_count' => count($bindings),
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
