<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime;

use App\Runtime\Exceptions\EnvironmentValidationException;
use App\Runtime\Validation\EnvValidator;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for EnvValidator — integration with the real
 * ConfigurationContract + EnvironmentContract from the container.
 *
 * Verifies the full required-key matrix against the actual testing
 * environment configured by phpunit.xml (sqlite :memory:, sync queue,
 * array cache).
 */
final class EnvValidatorTest extends TestCase
{
    #[Test]
    public function it_resolves_from_the_container(): void
    {
        $validator = $this->app->make(EnvValidator::class);

        $this->assertInstanceOf(EnvValidator::class, $validator);
    }

    #[Test]
    public function it_reports_zero_missing_required_keys_in_the_testing_environment(): void
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

    #[Test]
    public function assert_does_not_throw_in_the_testing_environment(): void
    {
        $validator = $this->app->make(EnvValidator::class);
        $env = $this->app->make(EnvironmentContract::class);

        // No exception expected.
        $validator->assert($env->type());
    }

    #[Test]
    public function the_testing_matrix_marks_db_host_and_database_url_as_optional(): void
    {
        $validator = $this->app->make(EnvValidator::class);

        $matrix = $validator->requiredKeys(EnvironmentType::Testing);

        $this->assertFalse($matrix['DB_HOST']);
        $this->assertFalse($matrix['DATABASE_URL']);
        $this->assertFalse($matrix['REDIS_HOST']);
        $this->assertFalse($matrix['NEON_BRANCH']);
    }

    #[Test]
    public function the_production_matrix_marks_payment_keys_as_required(): void
    {
        $validator = $this->app->make(EnvValidator::class);

        $matrix = $validator->requiredKeys(EnvironmentType::Production);

        $this->assertTrue($matrix['RAZORPAY_KEY_ID']);
        $this->assertTrue($matrix['RAZORPAY_KEY_SECRET']);
        $this->assertTrue($matrix['RAZORPAY_WEBHOOK_SECRET']);
        $this->assertTrue($matrix['PAYPAL_CLIENT_ID']);
        $this->assertTrue($matrix['PAYPAL_CLIENT_SECRET']);
    }

    #[Test]
    public function every_required_keys_matrix_includes_app_key(): void
    {
        $validator = $this->app->make(EnvValidator::class);

        foreach ([EnvironmentType::Local, EnvironmentType::Testing, EnvironmentType::CI, EnvironmentType::Production, EnvironmentType::Staging] as $type) {
            $matrix = $validator->requiredKeys($type);
            $this->assertTrue(
                $matrix['APP_KEY'] ?? false,
                "APP_KEY should be required in {$type->value} env",
            );
        }
    }
}