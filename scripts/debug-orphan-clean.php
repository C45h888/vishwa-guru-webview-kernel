<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$a = $app->make(\App\Persistence\Contracts\PersistenceAdapterContract::class);

$result = $a->query("SELECT id, slug FROM campaigns WHERE slug LIKE 'db-probe-%'");
if ($result->isFailure()) {
    fwrite(STDERR, "query failed: " . $result->error() . "\n");
    exit(1);
}

$rows = $result->value();
$deleted = 0;
foreach ($rows as $row) {
    $x = $a->execute("DELETE FROM campaigns WHERE id = :id", ['id' => $row['id']]);
    if ($x->isFailure()) {
        fwrite(STDERR, "delete " . $row['id'] . " failed: " . $x->error() . "\n");
    } else {
        echo "deleted " . $row['id'] . " (" . $row['slug'] . ")\n";
        $deleted++;
    }
}
echo "cleaned $deleted orphan campaigns\n";
