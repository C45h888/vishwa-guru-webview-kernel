<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$start = microtime(true);

/**
 * Reusable probe response helper — mirrors scripts/razorpay-probes
 * shape (lines 9-21 of rz01-config-and-credentials.php).
 *
 * @param  list<array{name: string, passed: bool}>  $checks
 * @param  array<string, mixed>  $evidence
 */
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

/**
 * Probe: every contract in CmsServiceProvider::provides()
 * resolves to a concrete instance. Doctrine: one binding per
 * interface, owned by the kernel provider.
 */
$provides = \App\Cms\Providers\CmsServiceProvider::class;
$reflection = new ReflectionClass($provides);
$method = $reflection->getMethod('provides');
$method->setAccessible(true);

// We'll need an instance to call ->provides() — Laravel guarantees
// the provider is bound by this point (guard at bootstrap). Fall back
// to the registered singleton.
$instance = app()->getProviders($provides);
$first = array_values($instance)[0] ?? null;
if (! $first) {
    cms_respond('cms01', [
        ['name' => 'CmsServiceProvider instance available', 'passed' => false],
    ], ['error' => 'No instance of CmsServiceProvider registered'], (int) ($start * 1000));
}
/** @var \App\Cms\Providers\CmsServiceProvider $first */
$contracts = $method->invoke($first);

$checks = [];
$resolveErrors = [];
foreach ($contracts as $contract) {
    try {
        $resolved = app($contract);
        $checks[] = [
            'name' => "{$contract} resolves",
            'passed' => $resolved !== null && is_object($resolved),
        ];
    } catch (\Throwable $e) {
        $resolveErrors[$contract] = $e->getMessage();
        $checks[] = [
            'name' => "{$contract} resolves",
            'passed' => false,
        ];
    }
}

cms_respond('cms01', $checks, [
    'contracts_provided' => count($contracts),
    'contracts_resolved' => count($contracts) - count($resolveErrors),
    'resolve_errors'      => $resolveErrors,
], (int) ($start * 1000));
