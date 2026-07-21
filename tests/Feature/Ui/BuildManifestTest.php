<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use Tests\TestCase;

final class BuildManifestTest extends TestCase
{
    public function test_build_manifest_is_well_formed_when_present(): void
    {
        $manifestPath = public_path('build/.vite/manifest.json');
        if (! is_file($manifestPath)) {
            $this->markTestSkipped(
                "public/build/.vite/manifest.json missing — run 'npm run build' before this test."
            );
        }

        $raw = file_get_contents($manifestPath);
        $this->assertIsString($raw);

        $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $entry = $decoded['resources/js/app.ts'] ?? $decoded['src/app.ts'] ?? null;
        $this->assertNotNull($entry, 'manifest is missing the app.ts entry');
        $this->assertNotEmpty($entry['file'] ?? null, 'manifest entry.file is empty');
        $this->assertStringStartsWith('assets/', (string) $entry['file']);
    }
}
