<?php

declare(strict_types=1);

namespace Tests\Feature\Bootstrap;

use Tests\TestCase;

/**
 * Locks the page-discovery root across the PHP config and the JS entry.
 *
 * Before this test the root lived as a literal in 4 places:
 *   - config/inertia.php (top-level page_paths)
 *   - config/inertia.php (testing.page_paths)
 *   - resources/js/app.ts (import.meta.glob)
 *   - resources/js/app.ts (resolvePage's template)
 *
 * The fix consolidates the PHP side around `config('inertia.js_pages_root')`
 * (with an `INERTIA_PAGES_ROOT` env override) and the JS side around a single
 * `const PAGES_ROOT = '...'` literal at the top of app.ts. This test asserts
 * the two declarations describe the same on-disk tree and stay in sync.
 */
final class InertiaPagesRootConsistencyTest extends TestCase
{
    public function test_config_and_app_ts_pages_root_match(): void
    {
        $phpRoot = config('inertia.js_pages_root');
        self::assertIsString($phpRoot, 'config(inertia.js_pages_root) must be a string.');
        self::assertNotEmpty($phpRoot, 'config(inertia.js_pages_root) must not be empty.');

        $tsSource = file_get_contents(base_path('resources/js/app.ts'));
        self::assertNotFalse($tsSource, 'resources/js/app.ts must exist and be readable.');

        if (! preg_match('/const\s+PAGES_ROOT\s*=\s*[\'"]([^\'"]+)[\'"]/', $tsSource, $matches)) {
            self::fail('resources/js/app.ts must declare `const PAGES_ROOT = "..."`.');
        }

        $tsRoot = $matches[1];
        $tsNormalized = preg_replace('/^\.\//', '', $tsRoot);

        self::assertSame(
            $phpRoot,
            $tsNormalized,
            "Page-discovery root mismatch. PHP config ('{$phpRoot}') must match ".
            "resources/js/app.ts PAGES_ROOT ('{$tsRoot}' normalized to '{$tsNormalized}').",
        );
    }

    public function test_page_paths_resolves_to_a_directory_with_svelte_files(): void
    {
        // Walk `config('inertia.js_pages_root')` and assert the directory
        // exists and contains at least one .svelte file. Catches the
        // "typo in the root string" failure mode at config-load time
        // rather than the first SSR request.
        $root = config('inertia.js_pages_root');
        self::assertIsString($root);

        $absolute = resource_path('js/' . $root);

        self::assertDirectoryExists(
            $absolute,
            "Page-discovery root '{$absolute}' must exist on disk.",
        );

        $svelteFiles = glob($absolute . '/**/*.svelte');
        self::assertNotFalse(
            $svelteFiles,
            "glob() failed for '{$absolute}/**/*.svelte'.",
        );
        self::assertNotEmpty(
            $svelteFiles,
            "Page-discovery root '{$absolute}' must contain at least one .svelte file.",
        );
    }

    public function test_app_ts_uses_the_pages_root_constant_in_both_glob_strings(): void
    {
        $tsSource = file_get_contents(base_path('resources/js/app.ts'));
        self::assertNotFalse($tsSource);

        // Two template-literal interpolations in app.ts must use PAGES_ROOT,
        // not a hard-coded './domains' string. Counts are deliberately ≥2:
        // the import.meta.glob and the resolvePage template. If a future
        // contributor pastes `./domains` back in, this fails.
        $usesConstant = substr_count($tsSource, '${PAGES_ROOT}');
        self::assertGreaterThanOrEqual(
            2,
            $usesConstant,
            'resources/js/app.ts must reference ${PAGES_ROOT} in at least two places (glob + resolvePage).',
        );

        // No bare './domains' literal should remain as a path-prefix.
        self::assertSame(
            0,
            preg_match('/[\'"`]\\.\\/domains\\//', $tsSource),
            'resources/js/app.ts must not contain hard-coded "./domains/" path literals.',
        );
    }
}
