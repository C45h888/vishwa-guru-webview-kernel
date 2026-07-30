<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * Pins the URL → Inertia page-name → Svelte-file triple.
 *
 * Walks every Route handler in routes/*.php and every controller in
 * app/Http/Controllers/Public/, parses `Inertia::render('a/b', ...)`
 * arguments, and asserts each page-name literal resolves to an
 * on-disk `.svelte` file under resources/js/domains/. Catches
 * filename / literal divergence (e.g. `Inertia::render('payments/Cancel',
 * ...)` paired with `CancelPage.svelte` instead of `Cancel.svelte`).
 *
 * The test does NOT exercise route resolution — it only asserts that
 * every Inertia page name declared in code has a matching file on
 * disk. The reverse direction (an on-disk file with no controller)
 * is a separate concern caught by the codegen + manual review.
 */
final class InertiaPageResolutionTest extends TestCase
{
    public function test_every_inertia_page_name_resolves_to_a_svelte_file(): void
    {
        $pages = $this->collectPageNames();
        self::assertNotEmpty($pages, 'No Inertia page names discovered.');

        $missing = [];
        foreach ($pages as $name) {
            $path = base_path("resources/js/domains/{$name}.svelte");
            if (! is_file($path)) {
                $missing[] = "{$name} → expected at {$path}";
            }
        }

        self::assertSame(
            [],
            $missing,
            "Inertia page names missing on disk:\n  " . implode("\n  ", $missing),
        );
    }

    public function test_no_orphan_inertia_files_in_resources_js_domains(): void
    {
        // Inverse: every `.svelte` file under resources/js/domains/ must
        // be referenced by at least one Inertia::render() call. Catches
        // files that survive after their controller was removed.
        $files = glob(base_path('resources/js/domains/**/*.svelte')) ?: [];
        $pages = $this->collectPageNames();

        $orphans = [];
        foreach ($files as $file) {
            $relative = str_replace(base_path('resources/js/domains/'), '', $file);
            $name = preg_replace('/\.svelte$/', '', $relative);
            if (! in_array($name, $pages, true)) {
                $orphans[] = $name;
            }
        }

        self::assertSame(
            [],
            $orphans,
            "Orphan Inertia pages (no controller references them):\n  " . implode("\n  ", $orphans),
        );
    }

    /**
     * @return list<string>
     */
    private function collectPageNames(): array
    {
        $names = [];

        // Walk every route file in routes/ (route-level closures like
        // routes/donate.php's inline `Inertia::render('payments/Cancel', ...)`
        // live here, not in a controller).
        foreach (glob(base_path('routes/*.php')) ?: [] as $routeFile) {
            $source = (string) file_get_contents($routeFile);
            if (preg_match_all("/Inertia::render\\(\\s*['\"]([^'\"]+)['\"]/", $source, $m)) {
                $names = array_merge($names, $m[1]);
            }
        }

        // Walk every controller class in app/Http/Controllers/Public/
        // (most Inertia::render() calls live here).
        foreach (glob(base_path('app/Http/Controllers/Public/**/*.php')) ?: [] as $ctrl) {
            $source = (string) file_get_contents($ctrl);
            if (preg_match_all("/Inertia::render\\(\\s*['\"]([^'\"]+)['\"]/", $source, $m)) {
                $names = array_merge($names, $m[1]);
            }
        }

        return array_values(array_unique($names));
    }
}