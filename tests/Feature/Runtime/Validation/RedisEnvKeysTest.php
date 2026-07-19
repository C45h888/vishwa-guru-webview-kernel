<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Validation;

use App\Runtime\Validation\EnvValidator;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Verifies that the EnvValidator's Production matrix includes Redis-specific
 * env keys, so `temple:env` surfaces Redis configuration gaps at boot
 * (rather than failing mysteriously mid-request).
 *
 * These keys were silently absent before Phase J — Production deploys could
 * boot, then break at the first cache or queue call. This test pins the
 * matrix so future edits don't accidentally drop Redis.
 */
final class RedisEnvKeysTest extends TestCase
{
    #[Test]
    public function production_matrix_includes_core_redis_keys(): void
    {
        $validator = $this->app->make(EnvValidator::class);

        $matrix = $validator->requiredKeys(EnvironmentType::Production);

        // Host OR URL — at least one of these is required.
        $this->assertTrue($matrix['REDIS_HOST'] ?? false, 'REDIS_HOST must be required in production');
        $this->assertFalse($matrix['REDIS_URL'] ?? true, 'REDIS_URL must be the OR-alternative');

        // Mandatory Redis connection settings.
        $this->assertTrue($matrix['REDIS_PORT'] ?? false, 'REDIS_PORT must be required');
        $this->assertTrue($matrix['REDIS_CLIENT'] ?? false, 'REDIS_CLIENT must be required (phpredis or predis)');
        $this->assertTrue($matrix['REDIS_PREFIX'] ?? false, 'REDIS_PREFIX must be required (avoids key collisions)');
        $this->assertTrue($matrix['REDIS_DB'] ?? false, 'REDIS_DB must be required (logical DB number)');
        $this->assertTrue($matrix['REDIS_PASSWORD'] ?? false, 'REDIS_PASSWORD must be required in production');
    }

    #[Test]
    public function production_matrix_includes_redis_db_assignments(): void
    {
        $validator = $this->app->make(EnvValidator::class);

        $matrix = $validator->requiredKeys(EnvironmentType::Production);

        // Each subsystem has its own logical DB.
        $this->assertTrue($matrix['REDIS_CACHE_DB'] ?? false, 'REDIS_CACHE_DB must be required');
        $this->assertTrue($matrix['REDIS_QUEUE_DB'] ?? false, 'REDIS_QUEUE_DB must be required');
        $this->assertTrue($matrix['REDIS_SESSION_DB'] ?? false, 'REDIS_SESSION_DB must be required');
    }

    #[Test]
    public function testing_matrix_does_not_require_redis_keys(): void
    {
        // SQLite + array cache + sync queue in testing — Redis should
        // NOT be required. temple:env in testing should never complain.
        $validator = $this->app->make(EnvValidator::class);

        $matrix = $validator->requiredKeys(EnvironmentType::Testing);

        $this->assertFalse($matrix['REDIS_HOST'] ?? true, 'REDIS_HOST should NOT be required in testing');
        $this->assertFalse($matrix['REDIS_PORT'] ?? true, 'REDIS_PORT should NOT be required in testing');
        $this->assertFalse($matrix['REDIS_PASSWORD'] ?? true, 'REDIS_PASSWORD should NOT be required in testing');
        $this->assertFalse($matrix['REDIS_PREFIX'] ?? true, 'REDIS_PREFIX should NOT be required in testing');
    }

    #[Test]
    public function production_matrix_redis_host_or_url_satisfied_by_either(): void
    {
        // Simulate Production env with only REDIS_HOST (no REDIS_URL).
        $envMock = $this->createMock(EnvironmentContract::class);
        $envMock->method('type')->willReturn(EnvironmentType::Production);
        $envMock->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null) => match ($key) {
                'REDIS_HOST' => 'redis.example.com',
                'REDIS_PORT' => '6379',
                'REDIS_CLIENT' => 'phpredis',
                'REDIS_PREFIX' => 'temple_trust_',
                'REDIS_DB' => '0',
                'REDIS_PASSWORD' => 'secret',
                'REDIS_CACHE_DB' => '1',
                'REDIS_QUEUE_DB' => '2',
                'REDIS_SESSION_DB' => '3',
                default => $default,
            },
        );
        $this->app->instance(EnvironmentContract::class, $envMock);
        $this->app->forgetInstance(EnvValidator::class);

        $validator = $this->app->make(EnvValidator::class);
        $missing = $validator->missingKeys(EnvironmentType::Production);

        $this->assertNotContains('REDIS_HOST', $missing, 'REDIS_HOST should be satisfied by its own presence');
        $this->assertNotContains('REDIS_URL', $missing, 'REDIS_URL is the OR-alternative — not flagged as missing when REDIS_HOST is present');
    }
}