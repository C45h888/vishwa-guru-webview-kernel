<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence\Neon;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Doctrine compliance — verifies the module boundary between
 * `App\Persistence\Neon\` and `App\Runtime\` is one-directional:
 *
 *   Runtime ── depends on ──▶ Persistence\Neon (probe implementation)
 *   Persistence\Neon ── does NOT depend on ──▶ Runtime (sees Runtime only via interfaces)
 *
 * The only Persistence\Neon → Runtime coupling permitted is via the
 * `HealthProbe` interface (which is the contract Runtime owns). Concrete
 * Runtime classes (FailureRouter, KernelSnapshot, etc.) must not be
 * referenced from Persistence\Neon.
 *
 * This is a static-analysis-flavored test. It scans PHP files in
 * app/Persistence/Neon/ for forbidden imports.
 */
final class DoctrineComplianceTest extends TestCase
{
    /**
     * Classes that Persistence\Neon MAY import from App\Runtime.
     * These are the interface boundaries, not concrete implementations.
     *
     * @return array<int, string>
     */
    private function allowedRuntimeImports(): array
    {
        return [
            'App\\Runtime\\Diagnostics\\HealthCheckResult',
            'App\\Runtime\\Diagnostics\\HealthProbe',
            'App\\Runtime\\Failure\\Contracts\\FailureReportingContract',
            'App\\Runtime\\Failure\\Enums\\FailureKind',
            'App\\Runtime\\Failure\\Enums\\FailureState',
            'App\\Runtime\\Failure\\StateMachines\\FailureTransitionResult',
            'App\\Runtime\\Failure\\ValueObjects\\FailureRecord',
        ];
    }

    /**
     * Concrete Runtime classes that Persistence\Neon MUST NOT import.
     *
     * @return array<int, string>
     */
    private function forbiddenRuntimeImports(): array
    {
        return [
            'App\\Runtime\\Diagnostics\\HealthCheckAggregator',
            'App\\Runtime\\Diagnostics\\KernelSnapshot',
            'App\\Runtime\\Diagnostics\\KernelSnapshotFactory',
            'App\\Runtime\\Diagnostics\\DatabaseHealthProbe',
            'App\\Runtime\\Diagnostics\\CacheHealthProbe',
            'App\\Runtime\\Diagnostics\\QueueHealthProbe',
            'App\\Runtime\\Failure\\FailureRouter',
            'App\\Runtime\\Failure\\StateMachines\\FailureStateMachine',
            'App\\Runtime\\Failure\\Handlers\\BootFailureHandler',
            'App\\Runtime\\Failure\\Handlers\\ProbeFailureHandler',
            'App\\Runtime\\Failure\\Handlers\\CommandFailureHandler',
            'App\\Runtime\\Failure\\Handlers\\HttpFailureHandler',
            'App\\Runtime\\Http\\Controllers\\HealthController',
            'App\\Runtime\\Http\\Controllers\\PingController',
            'App\\Runtime\\Console\\Commands\\RuntimeStatusCommand',
            'App\\Runtime\\Console\\Commands\\EnvironmentListCommand',
            'App\\Runtime\\Validation\\BootProbe',
            'App\\Runtime\\Validation\\EnvValidator',
            'App\\Runtime\\Providers\\RuntimeServiceProvider',
        ];
    }

    #[Test]
    public function persistence_neon_does_not_import_forbidden_runtime_concrete_classes(): void
    {
        $fs = new Filesystem();
        $neonPath = app_path('Persistence/Neon');

        if (! $fs->isDirectory($neonPath)) {
            $this->markTestSkipped('App\Persistence\Neon does not exist');
        }

        $violations = [];
        $forbidden = $this->forbiddenRuntimeImports();

        foreach ($fs->allFiles($neonPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($neonPath . '/', '', $file->getPathname());
            $contents = $fs->get($file->getPathname());

            foreach ($forbidden as $forbiddenClass) {
                // Look for use statements and inline references.
                $patterns = [
                    "/^use\s+{$forbiddenClass}\s*;/m",
                    "/\\\\{$forbiddenClass}\b/",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $contents)) {
                        $violations[] = "{$relativePath}: forbidden import of {$forbiddenClass}";
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Persistence\\Neon must not import concrete Runtime classes (use interfaces only).\n"
                . implode("\n", $violations),
        );
    }

    #[Test]
    public function persistence_neon_does_not_import_db_facade_directly(): void
    {
        $fs = new Filesystem();
        $neonPath = app_path('Persistence/Neon');

        if (! $fs->isDirectory($neonPath)) {
            $this->markTestSkipped('App\Persistence\Neon does not exist');
        }

        $violations = [];

        foreach ($fs->allFiles($neonPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($neonPath . '/', '', $file->getPathname());
            $contents = $fs->get($file->getPathname());

            // Look for actual DB:: calls (not just the word in a comment).
            if (preg_match('/\bDB::(?!connection)\w+\(/', $contents)) {
                $violations[] = "{$relativePath}: direct DB:: facade call (use PersistenceAdapterContract instead)";
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Persistence\\Neon must use PersistenceAdapterContract, not DB:: facade.\n"
                . implode("\n", $violations),
        );
    }

    #[Test]
    public function persistence_neon_does_not_call_env_directly(): void
    {
        $fs = new Filesystem();
        $neonPath = app_path('Persistence/Neon');

        if (! $fs->isDirectory($neonPath)) {
            $this->markTestSkipped('App\Persistence\Neon does not exist');
        }

        $violations = [];

        foreach ($fs->allFiles($neonPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($neonPath . '/', '', $file->getPathname());
            $contents = $fs->get($file->getPathname());

            // Look for env() calls outside of docblocks.
            // Strip docblock lines (lines starting with * or // or /** or */).
            $strippedContents = preg_replace('@^\s*\*.*$@m', '', $contents);

            if (preg_match('/\benv\s*\(/', $strippedContents)) {
                $violations[] = "{$relativePath}: direct env() call (use ConfigurationContract instead)";
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Persistence\\Neon must use ConfigurationContract, not env() directly.\n"
                . implode("\n", $violations),
        );
    }
}