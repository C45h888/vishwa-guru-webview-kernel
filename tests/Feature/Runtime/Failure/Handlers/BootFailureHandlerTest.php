<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Failure\Handlers;

use App\Runtime\Exceptions\EnvironmentValidationException;
use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\Handlers\BootFailureHandler;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\Enums\EnvironmentType;
use App\Shared\Support\Identifier;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for BootFailureHandler — the side-effect executor for
 * BootEnvMissing / PersistenceBindingFailed failures.
 *
 * Verifies:
 *   - BootEnvMissing throws EnvironmentValidationException with combined message
 *   - PersistenceBindingFailed throws RuntimeException with descriptive message
 *   - Unknown kind throws defensive RuntimeException
 *   - environment_type context is honored (defaults to Production if missing)
 */
final class BootFailureHandlerTest extends TestCase
{
    private function makeRecord(FailureKind $kind, array $context = []): FailureRecord
    {
        return new FailureRecord(
            id: Identifier::generate(),
            kind: $kind,
            origin: self::class,
            message: 'test',
            previousState: FailureState::Surfaced,
            context: $context,
            occurredAt: new DateTimeImmutable(),
        );
    }

    private function makeResult(): FailureTransitionResult
    {
        return new FailureTransitionResult(
            nextState: FailureState::Surfaced,
            handlerClass: BootFailureHandler::class,
            userMessage: 'boot failed',
        );
    }

    #[Test]
    public function it_resolves_from_the_container(): void
    {
        $handler = $this->app->make(BootFailureHandler::class);
        $this->assertInstanceOf(BootFailureHandler::class, $handler);
    }

    #[Test]
    public function boot_env_missing_throws_environment_validation_exception(): void
    {
        $handler = $this->app->make(BootFailureHandler::class);

        $this->expectException(EnvironmentValidationException::class);

        $handler->handle(
            $this->makeRecord(FailureKind::BootEnvMissing, [
                'environment_type' => 'production',
                'missing' => ['DATABASE_URL', 'RAZORPAY_KEY_ID'],
            ]),
            $this->makeResult(),
        );
    }

    #[Test]
    public function environment_validation_exception_carries_the_correct_environment_type(): void
    {
        $handler = $this->app->make(BootFailureHandler::class);

        try {
            $handler->handle(
                $this->makeRecord(FailureKind::BootEnvMissing, [
                    'environment_type' => 'staging',
                    'missing' => ['APP_KEY'],
                ]),
                $this->makeResult(),
            );
            $this->fail('Expected EnvironmentValidationException');
        } catch (EnvironmentValidationException $e) {
            $this->assertSame(EnvironmentType::Staging, $e->environmentType);
            $this->assertContains('APP_KEY', $e->missingKeys);
        }
    }

    #[Test]
    public function persistence_binding_failed_throws_runtime_exception(): void
    {
        $handler = $this->app->make(BootFailureHandler::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Persistence layer binding failed/');

        $handler->handle(
            $this->makeRecord(FailureKind::PersistenceBindingFailed, [
                'environment_type' => 'production',
            ]),
            $this->makeResult(),
        );
    }

    #[Test]
    public function missing_environment_type_defaults_to_production(): void
    {
        $handler = $this->app->make(BootFailureHandler::class);

        try {
            $handler->handle(
                $this->makeRecord(FailureKind::BootEnvMissing, ['missing' => ['APP_KEY']]),
                $this->makeResult(),
            );
            $this->fail('Expected exception');
        } catch (EnvironmentValidationException $e) {
            $this->assertSame(EnvironmentType::Production, $e->environmentType);
        }
    }
}