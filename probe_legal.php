<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$disk = \Illuminate\Support\Facades\Storage::disk('local');
foreach (['eighty_g.pdf', 'twelve_a.pdf', 'poa.pdf', 'tan.pdf'] as $f) {
    $exists = $disk->exists('legalmedia/assets/' . $f);
    $size = $exists ? $disk->size('legalmedia/assets/' . $f) : -1;
    echo str_pad($f, 20) . ' exists=' . var_export($exists, true) . ' size=' . $size . PHP_EOL;
}
