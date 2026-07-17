<?php

declare(strict_types=1);

namespace Tests\Unit\Persistence\Neon\ValueObjects;

use App\Persistence\Neon\ValueObjects\NeonConnectionConfig;
use App\Persistence\Neon\ValueObjects\NeonRole;
use App\Shared\Contracts\ConfigurationContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for NeonConnectionConfig — the password-never-present
 * invariant is the doctrine-critical guarantee.
 */
final class NeonConnectionConfigTest extends TestCase
{
    private function makeConfig(array $values): ConfigurationContract
    {
        $mock = $this->createMock(ConfigurationContract::class);
        $mock->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null) => $values[$key] ?? $default,
        );
        return $mock;
    }

    #[Test]
    public function parses_host_database_username_from_database_url(): void
    {
        $config = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://app_user:s3cret@ep-cool-name.us-east-2.aws.neon.tech/neondb?sslmode=require&channel_binding=require',
        ]);

        $parsed = NeonConnectionConfig::fromConfig($config);

        $this->assertSame('ep-cool-name.us-east-2.aws.neon.tech', $parsed->host);
        $this->assertSame('neondb', $parsed->database);
        $this->assertSame('app_user', $parsed->username);
        $this->assertSame('require', $parsed->sslmode);
        $this->assertSame('require', $parsed->channelBinding);
    }

    #[Test]
    public function the_password_is_never_present_in_the_vo(): void
    {
        $config = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://app_user:s3cret_password_xyz@ep-foo.neon.tech/neondb',
        ]);

        $parsed = NeonConnectionConfig::fromConfig($config);

        // Check via reflection — make sure no field is named "password"
        $reflection = new \ReflectionClass($parsed);
        foreach ($reflection->getProperties() as $prop) {
            $this->assertNotSame(
                'password',
                strtolower($prop->getName()),
                "NeonConnectionConfig must not have a 'password' property (found: {$prop->getName()})",
            );
        }

        // Also check toArray output
        $array = $parsed->toArray();
        $this->assertArrayNotHasKey('password', $array);
        foreach ($array as $key => $value) {
            $this->assertStringNotContainsString(
                's3cret_password_xyz',
                (string) $value,
                "Field {$key} must not contain the password",
            );
        }
    }

    #[Test]
    public function falls_back_to_discrete_env_keys_when_url_is_absent(): void
    {
        $config = $this->makeConfig([
            'database.connections.neon.host' => '127.0.0.1',
            'database.connections.neon.database' => 'temple_trust',
            'database.connections.neon.username' => 'app_user',
            'database.connections.neon.sslmode' => 'require',
        ]);

        $parsed = NeonConnectionConfig::fromConfig($config);

        $this->assertSame('127.0.0.1', $parsed->host);
        $this->assertSame('temple_trust', $parsed->database);
        $this->assertSame('app_user', $parsed->username);
        $this->assertSame('require', $parsed->sslmode);
    }

    #[Test]
    public function falls_back_to_pgsql_connection_keys_when_neon_block_absent(): void
    {
        $config = $this->makeConfig([
            'database.connections.pgsql.host' => '127.0.0.1',
            'database.connections.pgsql.database' => 'temple_trust',
            'database.connections.pgsql.username' => 'app_user',
            'database.connections.pgsql.sslmode' => 'prefer',
        ]);

        $parsed = NeonConnectionConfig::fromConfig($config);

        $this->assertSame('127.0.0.1', $parsed->host);
        $this->assertSame('temple_trust', $parsed->database);
        $this->assertSame('app_user', $parsed->username);
        $this->assertSame('prefer', $parsed->sslmode);
    }

    #[Test]
    public function role_parses_from_services_neon_role_env_key(): void
    {
        $config = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d',
            'services.neon.role' => 'owner',
        ]);

        $parsed = NeonConnectionConfig::fromConfig($config);

        $this->assertSame(NeonRole::Owner, $parsed->role);
    }

    #[Test]
    public function role_defaults_to_app_for_unknown_values(): void
    {
        $config = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d',
            'services.neon.role' => 'admin', // unknown
        ]);

        $parsed = NeonConnectionConfig::fromConfig($config);

        $this->assertSame(NeonRole::App, $parsed->role);
    }

    #[Test]
    public function is_pooled_detects_pgbouncer_query_flag(): void
    {
        $configWith = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d?pgbouncer=true',
        ]);
        $configWithout = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d',
        ]);

        $this->assertTrue(NeonConnectionConfig::fromConfig($configWith)->isPooled);
        $this->assertFalse(NeonConnectionConfig::fromConfig($configWithout)->isPooled);
    }

    #[Test]
    public function hasSecureSslMode_returns_true_only_for_require_or_stricter(): void
    {
        $secure = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d?sslmode=require',
        ]);
        $verifyCa = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d?sslmode=verify-ca',
        ]);
        $verifyFull = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d?sslmode=verify-full',
        ]);
        $prefer = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d?sslmode=prefer',
        ]);
        $none = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d',
        ]);

        $this->assertTrue(NeonConnectionConfig::fromConfig($secure)->hasSecureSslMode());
        $this->assertTrue(NeonConnectionConfig::fromConfig($verifyCa)->hasSecureSslMode());
        $this->assertTrue(NeonConnectionConfig::fromConfig($verifyFull)->hasSecureSslMode());
        $this->assertFalse(NeonConnectionConfig::fromConfig($prefer)->hasSecureSslMode());
        $this->assertFalse(NeonConnectionConfig::fromConfig($none)->hasSecureSslMode());
    }

    #[Test]
    public function application_name_parses_from_neon_block(): void
    {
        $config = $this->makeConfig([
            'database.connections.neon.url' => 'postgresql://u:p@h/d',
            'database.connections.neon.application_name' => 'temple-trust',
        ]);

        $parsed = NeonConnectionConfig::fromConfig($config);

        $this->assertSame('temple-trust', $parsed->applicationName);
    }

    #[Test]
    public function toArray_renders_missing_fields_as_not_set(): void
    {
        $config = $this->makeConfig([]); // empty
        $parsed = NeonConnectionConfig::fromConfig($config);

        $array = $parsed->toArray();

        $this->assertSame('(not set)', $array['host']);
        $this->assertSame('(not set)', $array['database']);
        $this->assertSame('(not set)', $array['username']);
        $this->assertSame('app (read-write)', $array['role']); // default
        $this->assertSame('(not set)', $array['sslmode']);
    }
}