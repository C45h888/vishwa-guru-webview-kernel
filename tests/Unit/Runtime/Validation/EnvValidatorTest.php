<?php

declare(strict_types=1);

namespace Tests\Unit\Runtime\Validation;

use App\Runtime\Exceptions\EnvironmentValidationException;
use App\Runtime\Validation\EnvValidator;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for EnvValidator.
 * Mocks ConfigurationContract + EnvironmentContract — no container, no .env reads.
 */
final class EnvValidatorTest extends TestCase
{
    private function makeEnv(array $values): EnvironmentContract
    {
        $mock = $this->createMock(EnvironmentContract::class);
        $mock->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null) => $values[$key] ?? $default
        );
        return $mock;
    }

    private function makeConfig(): ConfigurationContract
    {
        return $this->createMock(ConfigurationContract::class);
    }

    #[Test]
    public function production_matrix_includes_all_required_keys(): void
    {
        $validator = new EnvValidator($this->makeConfig(), $this->makeEnv([]));

        $matrix = $validator->requiredKeys(EnvironmentType::Production);

        $this->assertTrue($matrix['APP_KEY']);
        $this->assertTrue($matrix['APP_URL']);
        $this->assertTrue($matrix['DB_CONNECTION']);
        $this->assertTrue($matrix['DATABASE_URL']);
        $this->assertTrue($matrix['CACHE_STORE']);
        $this->assertTrue($matrix['QUEUE_CONNECTION']);
        $this->assertTrue($matrix['REDIS_HOST']);
        $this->assertTrue($matrix['NEON_BRANCH']);
        $this->assertTrue($matrix['NEON_ROLE']);
        $this->assertTrue($matrix['RAZORPAY_KEY_ID']);
        $this->assertTrue($matrix['RAZORPAY_KEY_SECRET']);
        $this->assertTrue($matrix['RAZORPAY_WEBHOOK_SECRET']);
        $this->assertTrue($matrix['PAYPAL_CLIENT_ID']);
        $this->assertTrue($matrix['PAYPAL_CLIENT_SECRET']);
    }

    #[Test]
    public function production_assert_returns_silently_when_all_required_keys_present(): void
    {
        $validator = new EnvValidator($this->makeConfig(), $this->makeEnv([
            'APP_KEY' => 'base64:abc123',
            'APP_URL' => 'https://temple.test',
            'DB_CONNECTION' => 'pgsql',
            'DATABASE_URL' => 'postgresql://user:pass@host/db',
            'CACHE_STORE' => 'redis',
            'QUEUE_CONNECTION' => 'redis',
            'REDIS_HOST' => '127.0.0.1',
            'NEON_BRANCH' => 'main',
            'NEON_ROLE' => 'app',
            'RAZORPAY_KEY_ID' => 'rzp_test_1',
            'RAZORPAY_KEY_SECRET' => 'secret_1',
            'RAZORPAY_WEBHOOK_SECRET' => 'webhook_1',
            'PAYPAL_CLIENT_ID' => 'client_1',
            'PAYPAL_CLIENT_SECRET' => 'secret_1',
        ]));

        // No throw expected.
        $validator->assert(EnvironmentType::Production);
        $this->assertSame([], $validator->missingKeys(EnvironmentType::Production));
    }

    #[Test]
    public function production_assert_throws_with_combined_message_when_keys_missing(): void
    {
        $validator = new EnvValidator($this->makeConfig(), $this->makeEnv([
            'APP_KEY' => 'base64:abc123',
            // APP_URL missing
            // DB_CONNECTION missing
            'DATABASE_URL' => 'postgresql://user:pass@host/db',
            'CACHE_STORE' => 'redis',
            'QUEUE_CONNECTION' => 'redis',
            'REDIS_HOST' => '127.0.0.1',
            'NEON_BRANCH' => 'main',
            'NEON_ROLE' => 'app',
            'RAZORPAY_KEY_ID' => 'rzp_test_1',
            'RAZORPAY_KEY_SECRET' => 'secret_1',
            'RAZORPAY_WEBHOOK_SECRET' => 'webhook_1',
            'PAYPAL_CLIENT_ID' => 'client_1',
            'PAYPAL_CLIENT_SECRET' => 'secret_1',
        ]));

        $this->expectException(EnvironmentValidationException::class);
        $this->expectExceptionMessageMatches('/Environment validation failed for Production.*missing/');

        try {
            $validator->assert(EnvironmentType::Production);
        } catch (EnvironmentValidationException $e) {
            $this->assertSame(EnvironmentType::Production, $e->environmentType);
            $this->assertContains('APP_URL', $e->missingKeys);
            $this->assertContains('DB_CONNECTION', $e->missingKeys);
            // Message contains actionable hints
            $this->assertStringContainsString('APP_URL', $e->getMessage());
            $this->assertStringContainsString('DB_CONNECTION', $e->getMessage());
            throw $e;
        }
    }

    #[Test]
    public function testing_matrix_does_not_require_db_host_or_database_url(): void
    {
        $validator = new EnvValidator($this->makeConfig(), $this->makeEnv([
            'APP_KEY' => 'base64:abc',
            'APP_URL' => 'http://localhost',
            'DB_CONNECTION' => 'sqlite',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]));

        // No DB_HOST, no DATABASE_URL — should not be flagged.
        $this->assertSame([], $validator->missingKeys(EnvironmentType::Testing));
    }

    #[Test]
    public function local_matrix_accepts_either_db_host_or_database_url(): void
    {
        // Local with DATABASE_URL only — DB_HOST missing should be satisfied via OR-group.
        $validator = new EnvValidator($this->makeConfig(), $this->makeEnv([
            'APP_KEY' => 'base64:abc',
            'APP_URL' => 'http://localhost',
            'DB_CONNECTION' => 'pgsql',
            'DATABASE_URL' => 'postgresql://user:pass@host/db',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ]));

        $missing = $validator->missingKeys(EnvironmentType::Local);
        $this->assertNotContains('DB_HOST', $missing);
        $this->assertNotContains('DATABASE_URL', $missing);
    }

    #[Test]
    public function empty_string_is_treated_as_missing(): void
    {
        $validator = new EnvValidator($this->makeConfig(), $this->makeEnv([
            'APP_KEY' => 'base64:abc',
            'APP_URL' => 'http://localhost',
            'DB_CONNECTION' => '',  // empty
            'DATABASE_URL' => 'postgresql://user:pass@host/db',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ]));

        $missing = $validator->missingKeys(EnvironmentType::Local);
        $this->assertContains('DB_CONNECTION', $missing);
    }

    #[Test]
    public function literal_null_string_is_treated_as_missing(): void
    {
        $validator = new EnvValidator($this->makeConfig(), $this->makeEnv([
            'APP_KEY' => 'null',
            'APP_URL' => 'http://localhost',
            'DB_CONNECTION' => 'pgsql',
            'DATABASE_URL' => 'postgresql://user:pass@host/db',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
        ]));

        $missing = $validator->missingKeys(EnvironmentType::Local);
        $this->assertContains('APP_KEY', $missing);
    }

    #[Test]
    public function staging_uses_the_same_matrix_as_production(): void
    {
        $validator = new EnvValidator($this->makeConfig(), $this->makeEnv([]));

        $this->assertSame(
            $validator->requiredKeys(EnvironmentType::Production),
            $validator->requiredKeys(EnvironmentType::Staging),
        );
    }

    #[Test]
    public function exception_message_lists_each_missing_key_with_actionable_hint(): void
    {
        $exception = EnvironmentValidationException::fromMissingKeys(
            EnvironmentType::Production,
            ['APP_KEY', 'DATABASE_URL'],
        );

        $message = $exception->getMessage();

        $this->assertStringContainsString('Production', $message);
        $this->assertStringContainsString('APP_KEY', $message);
        $this->assertStringContainsString('php artisan key:generate', $message);
        $this->assertStringContainsString('DATABASE_URL', $message);
        $this->assertStringContainsString('sslmode=require', $message);
    }
}