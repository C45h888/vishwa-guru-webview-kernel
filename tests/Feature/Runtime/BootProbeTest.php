<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime;

use App\Runtime\Exceptions\EnvironmentValidationException;
use App\Runtime\Validation\BootProbe;
use App\Runtime\Validation\EnvValidator;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for BootProbe — boot-time fail-fast assertion.
 *
 * Covers:
 *   - successful assert in testing env (all keys present)
 *   - thrown EnvironmentValidationException when env=production + keys missing
 *   - idempotency: alreadyRan guard short-circuits subsequent calls
 *
 * Resets BootProbe::$alreadyRan in setUp/tearDown to prevent static
 * state leakage between tests (PHPUnit runs all tests in one process).
 */
final class BootProbeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BootProbe::resetForTesting();
    }

    protected function tearDown(): void
    {
        BootProbe::resetForTesting();
        parent::tearDown();
    }

    #[Test]
    public function assert_succeeds_silently_in_the_testing_environment(): void
    {
        $probe = $this->app->make(BootProbe::class);

        // Should not throw.
        $probe->assert();

        $this->assertTrue($probe->alreadyRan());
    }

    #[Test]
    public function alreadyRan_returns_false_before_assert_and_true_after(): void
    {
        $probe = $this->app->make(BootProbe::class);

        $this->assertFalse($probe->alreadyRan());

        $probe->assert();

        $this->assertTrue($probe->alreadyRan());
    }

    #[Test]
    public function assert_is_idempotent_within_a_process(): void
    {
        $probe = $this->app->make(BootProbe::class);

        $probe->assert();
        // Second call should short-circuit via the static guard.
        $probe->assert();
        $probe->assert();

        // No exception, no extra work — just confirms the guard works.
        $this->assertTrue($probe->alreadyRan());
    }

    #[Test]
    public function assert_throws_when_environment_is_production_and_required_keys_missing(): void
    {
        // Force the contract's type() to return Production without
        // changing APP_ENV globally (avoiding test-env side-effects).
        $envMock = $this->createMock(EnvironmentContract::class);
        $envMock->method('type')->willReturn(EnvironmentType::Production);
        $envMock->method('get')->willReturn(null);

        $this->app->instance(EnvironmentContract::class, $envMock);

        // Rebuild EnvValidator + BootProbe against the mocked env.
        $this->app->forgetInstance(EnvValidator::class);
        $this->app->forgetInstance(BootProbe::class);

        $probe = $this->app->make(BootProbe::class);

        $this->expectException(EnvironmentValidationException::class);
        $this->expectExceptionMessageMatches('/Environment validation failed for Production/');

        $probe->assert();
    }

    #[Test]
    public function env_validator_can_be_resolved_from_the_container(): void
    {
        $validator = $this->app->make(EnvValidator::class);

        $this->assertInstanceOf(EnvValidator::class, $validator);
    }

    #[Test]
    public function the_real_testing_environment_has_no_missing_required_keys(): void
    {
        $validator = $this->app->make(EnvValidator::class);
        $env = $this->app->make(EnvironmentContract::class);

        $missing = $validator->missingKeys($env->type());

        $this->assertSame(
            [],
            $missing,
            'Testing environment should not have any missing required keys; got: '.implode(', ', $missing),
        );
    }
}