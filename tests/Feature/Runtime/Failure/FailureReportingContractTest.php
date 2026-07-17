<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Failure;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Routing-membrane guarantee — every failure in the system passes
 * through FailureReportingContract. No code path may instantiate a
 * handler directly outside the router.
 *
 * This is a static-analysis-flavored test: it scans the codebase for
 * handler instantiations and fails if any are found outside
 * App\Runtime\Failure\FailureRouter.php.
 *
 * Doctrine:
 *   - FailureReportingContract is the only public entry point for failures
 *   - The router dispatches to handlers; domain code does NOT
 *   - Adding a new direct handler call is a regression — this test catches it
 */
final class FailureReportingContractTest extends TestCase
{
    /**
     * Classes that are ALLOWED to instantiate handlers directly.
     * Only the router itself.
     *
     * @return array<int, string>
     */
    private function allowedHandlerInstantiators(): array
    {
        return [
            'App\\Runtime\\Failure\\FailureRouter.php',
        ];
    }

    /**
     * Handler class names that should never appear in direct instantiation.
     *
     * @return array<int, string>
     */
    private function handlerClassNames(): array
    {
        return [
            'BootFailureHandler',
            'ProbeFailureHandler',
            'CommandFailureHandler',
            'HttpFailureHandler',
        ];
    }

    #[Test]
    public function no_handler_is_instantiated_directly_outside_the_router(): void
    {
        $fs = new Filesystem();
        $appPath = app_path();

        $violations = [];
        $allowedFiles = $this->allowedHandlerInstantiators();

        foreach ($fs->allFiles($appPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($appPath . '/', '', $file->getPathname());

            if (in_array('App\\Runtime\\Failure\\' . $relativePath, $allowedFiles, true)) {
                continue;
            }

            $contents = $fs->get($file->getPathname());

            foreach ($this->handlerClassNames() as $handlerName) {
                // Look for direct instantiation patterns: `new HandlerClass(` or
                // `app(HandlerClass::class)` outside the router.
                $patterns = [
                    "/\\bnew\\s+{$handlerName}\\s*\\(/",
                    "/\\bapp\\(\\s*{$handlerName}::class\\s*\\)/",
                ];

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $contents)) {
                        // Exclude legitimate references (in the FailureRouter
                        // or in test files — tests can instantiate handlers).
                        if (str_contains($file->getPathname(), '/tests/')) {
                            continue;
                        }
                        $violations[] = "{$relativePath}: direct instantiation of {$handlerName}";
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Direct handler instantiations found outside FailureRouter. Use FailureReportingContract::report() instead.\n"
                . implode("\n", $violations),
        );
    }

    #[Test]
    public function the_failure_reporting_contract_is_resolvable_from_the_container(): void
    {
        $contract = $this->app->make(\App\Runtime\Failure\Contracts\FailureReportingContract::class);

        $this->assertInstanceOf(\App\Runtime\Failure\FailureRouter::class, $contract);
    }

    #[Test]
    public function all_handler_classes_are_resolvable_from_the_container(): void
    {
        $handlers = [
            \App\Runtime\Failure\Handlers\BootFailureHandler::class,
            \App\Runtime\Failure\Handlers\ProbeFailureHandler::class,
            \App\Runtime\Failure\Handlers\CommandFailureHandler::class,
            \App\Runtime\Failure\Handlers\HttpFailureHandler::class,
        ];

        foreach ($handlers as $handlerClass) {
            $this->assertInstanceOf($handlerClass, $this->app->make($handlerClass));
        }
    }
}