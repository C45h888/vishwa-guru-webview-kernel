<?php

declare(strict_types=1);

namespace App\Runtime\Validation;

use App\Runtime\Exceptions\EnvironmentValidationException;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;

/**
 * Validates that the environment has every required key for the current
 * runtime target.
 *
 * Doctrine: this is the ONLY class that reads env() for runtime
 * structural concerns. Services everywhere else consume
 * ConfigurationContract (which reads config(), not env()).
 *
 * The required-key matrix is hard-coded in PHP — not config. Rationale:
 * putting it in config/runtime.php would invite ops to "tune" it,
 * defeating its purpose as a structural assertion.
 *
 * Wired as a singleton in RuntimeServiceProvider. Idempotent: can be
 * called multiple times safely.
 */
final class EnvValidator
{
    /**
     * OR-groupings: keys where at least ONE must be present.
     * Used by isKeySatisfied() to short-circuit the missing check.
     *
     * @var array<string, array<int, string>>
     */
    private const OR_GROUPS = [
        'DB_HOST' => ['DB_HOST', 'DATABASE_URL'],
        'DATABASE_URL' => ['DB_HOST', 'DATABASE_URL'],
        'REDIS_HOST' => ['REDIS_HOST', 'REDIS_URL'],
        'REDIS_URL' => ['REDIS_HOST', 'REDIS_URL'],
    ];

    public function __construct(
        private readonly ConfigurationContract $config,
        private readonly EnvironmentContract $env,
    ) {}

    /**
     * Required-key matrix for the given environment.
     * Returns key => required-flag. Required-flag is informational;
     * missingKeys() uses this map to decide what to enforce.
     *
     * @return array<string, bool>
     */
    public function requiredKeys(EnvironmentType $type): array
    {
        return match ($type) {
            EnvironmentType::Local => $this->localMatrix(),
            EnvironmentType::Testing => $this->testingMatrix(),
            EnvironmentType::CI => $this->ciMatrix(),
            EnvironmentType::Production, EnvironmentType::Staging => $this->productionMatrix(),
        };
    }

    /**
     * List of required keys that are currently missing in the env.
     *
     * @return array<int, string>
     */
    public function missingKeys(EnvironmentType $type): array
    {
        $matrix = $this->requiredKeys($type);
        $missing = [];

        foreach ($matrix as $key => $required) {
            if (! $required) {
                continue;
            }
            if (! $this->isKeySatisfied($key)) {
                $missing[] = $key;
            }
        }

        return array_values(array_unique($missing));
    }

    /**
     * Fail-fast assertion. Throws EnvironmentValidationException
     * listing every missing required key + actionable hints.
     */
    public function assert(EnvironmentType $type): void
    {
        $missing = $this->missingKeys($type);
        if ($missing !== []) {
            throw EnvironmentValidationException::fromMissingKeys($type, $missing);
        }
    }

    private function isKeySatisfied(string $key): bool
    {
        // OR-group handling: if any member of the group is present,
        // every member of the group is satisfied.
        if (isset(self::OR_GROUPS[$key])) {
            foreach (self::OR_GROUPS[$key] as $groupKey) {
                if ($this->envValuePresent($groupKey)) {
                    return true;
                }
            }
            return false;
        }

        return $this->envValuePresent($key);
    }

    private function envValuePresent(string $key): bool
    {
        $value = $this->env->get($key);

        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== '' && strtolower($trimmed) !== 'null';
        }

        return true;
    }

    /**
     * @return array<string, bool>
     */
    private function localMatrix(): array
    {
        return [
            'APP_KEY' => true,
            'APP_URL' => true,
            'DB_CONNECTION' => true,
            'DB_HOST' => true,         // OR DATABASE_URL — handled in isKeySatisfied()
            'DATABASE_URL' => false,
            'CACHE_STORE' => true,
            'QUEUE_CONNECTION' => true,
            'REDIS_HOST' => false,
            'REDIS_URL' => false,
            'NEON_BRANCH' => false,
            'NEON_ROLE' => false,
            'RAZORPAY_KEY_ID' => false,
            'RAZORPAY_KEY_SECRET' => false,
            'RAZORPAY_WEBHOOK_SECRET' => false,
            'PAYPAL_CLIENT_ID' => false,
            'PAYPAL_CLIENT_SECRET' => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function testingMatrix(): array
    {
        return [
            'APP_KEY' => true,
            'APP_URL' => true,
            'DB_CONNECTION' => true,
            // sqlite :memory: — no DB_HOST or DATABASE_URL needed
            'DB_HOST' => false,
            'DATABASE_URL' => false,
            'CACHE_STORE' => true,
            'QUEUE_CONNECTION' => true,
            'REDIS_HOST' => false,
            'REDIS_URL' => false,
            'NEON_BRANCH' => false,
            'NEON_ROLE' => false,
            'RAZORPAY_KEY_ID' => false,
            'RAZORPAY_KEY_SECRET' => false,
            'RAZORPAY_WEBHOOK_SECRET' => false,
            'PAYPAL_CLIENT_ID' => false,
            'PAYPAL_CLIENT_SECRET' => false,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function ciMatrix(): array
    {
        // CI runs like Testing — sqlite, sync queue, array cache.
        return $this->testingMatrix();
    }

    /**
     * @return array<string, bool>
     */
    private function productionMatrix(): array
    {
        $paypalEnabled = (bool) $this->config->get('payments.providers.paypal.enabled', false);

        return [
            'APP_KEY' => true,
            'APP_URL' => true,
            'DB_CONNECTION' => true,
            'DATABASE_URL' => true,   // OR DB_HOST — but Neon prod uses DATABASE_URL
            'DB_HOST' => false,
            'CACHE_STORE' => true,
            'QUEUE_CONNECTION' => true,
            'REDIS_HOST' => true,     // OR REDIS_URL — either satisfies the connect block
            'REDIS_URL' => false,
            'REDIS_PORT' => true,
            'REDIS_CLIENT' => true,   // phpredis (extension) or predis (Composer)
            'REDIS_PREFIX' => true,   // prevents key collisions in shared Redis instances
            'REDIS_DB' => true,       // logical DB 0 = app; 1=cache, 2=queue, 3=session are also configured
            'REDIS_PASSWORD' => true,  // required for production (managed Redis always has auth)
            'REDIS_CACHE_DB' => true,
            'REDIS_QUEUE_DB' => true,
            'REDIS_SESSION_DB' => true,
            'NEON_BRANCH' => true,
            'NEON_ROLE' => true,
            'RAZORPAY_KEY_ID' => true,
            'RAZORPAY_KEY_SECRET' => true,
            'RAZORPAY_WEBHOOK_SECRET' => true,
            // Razorpay is the sole public gateway for the Indian launch;
            // PayPal credentials are only required when explicitly enabled.
            // (See config/payments.php — PAYPAL_ENABLED defaults to false.)
            'PAYPAL_CLIENT_ID' => $paypalEnabled,
            'PAYPAL_CLIENT_SECRET' => $paypalEnabled,
            'PAYPAL_WEBHOOK_ID' => $paypalEnabled,
        ];
    }
}