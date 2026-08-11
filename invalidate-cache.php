<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cache = app(\App\Cms\Contracts\ResolvedPageCacheContract::class);
$slug = new \App\Cms\Domain\ValueObjects\PageSlug('legal');

// Invalidate the cached entry (regardless of schema version)
$cache->invalidate($slug);
echo 'CACHE_INVALIDATED' . PHP_EOL;

// Also try to clear any stale keys directly
try {
    $cache->invalidateAll();
    echo 'CACHE_INVALIDATE_ALL_OK' . PHP_EOL;
} catch (\Throwable $e) {
    echo 'INVALIDATE_ALL_ERR: ' . $e->getMessage() . PHP_EOL;
}
