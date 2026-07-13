<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Shared\Configuration\ConfigurationRegistry;
use App\Shared\Configuration\LaravelConfiguration;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use App\Shared\Support\Clock;
use App\Shared\Support\IdentifierGenerator;
use App\Shared\Support\SystemClock;
use App\Shared\Support\UlidGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests verifying the SharedServiceProvider bindings resolve
 * to the expected contracts and concrete implementations.
 */
final class SharedBindingsTest extends TestCase
{
    #[Test]
    public function it_resolves_the_clock_to_a_system_clock_by_default(): void
    {
        $clock = $this->app->make(Clock::class);

        $this->assertInstanceOf(SystemClock::class, $clock);
        $this->assertGreaterThan(0, $clock->timestamp());
    }

    #[Test]
    public function it_resolves_the_identifier_generator_to_ulid(): void
    {
        $generator = $this->app->make(IdentifierGenerator::class);

        $this->assertInstanceOf(UlidGenerator::class, $generator);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $generator->next());
    }

    #[Test]
    public function it_resolves_the_environment_contract(): void
    {
        $environment = $this->app->make(EnvironmentContract::class);

        $this->assertTrue($environment->isTesting());
        $this->assertSame(EnvironmentType::Testing, $environment->type());
    }

    #[Test]
    public function it_resolves_the_configuration_contract(): void
    {
        $configuration = $this->app->make(ConfigurationContract::class);

        $this->assertInstanceOf(LaravelConfiguration::class, $configuration);
        $this->assertSame('INR', $configuration->string('shared.money.default_currency'));
        $this->assertSame('ulid', $configuration->string('shared.identifiers.strategy'));
    }

    #[Test]
    public function it_registers_the_configuration_registry(): void
    {
        $registry = $this->app->make(ConfigurationRegistry::class);

        $this->assertTrue($registry->has('shared'));
        $this->assertTrue($registry->has('app'));
        $this->assertSame('testing', $registry->namespace('app')->string('env'));
    }
}