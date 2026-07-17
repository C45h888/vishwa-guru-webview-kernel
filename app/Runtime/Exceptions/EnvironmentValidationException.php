<?php

declare(strict_types=1);

namespace App\Runtime\Exceptions;

use App\Shared\Enums\EnvironmentType;
use RuntimeException;
use Throwable;

/**
 * Thrown when the application's environment fails runtime validation.
 *
 * Carries the EnvironmentType under which the failure was detected and
 * the list of missing required keys. The exception's message is a single
 * actionable block listing every missing key + how to set it.
 *
 * Doctrine: this exception is the ONE place the application refuses to
 * boot. BootProbe calls EnvValidator::assert() exactly once at
 * RuntimeServiceProvider::boot() and lets this exception propagate to
 * Laravel's default exception handler.
 */
final class EnvironmentValidationException extends RuntimeException
{
    /**
     * @param  array<int, string>  $missingKeys
     */
    private function __construct(
        string $message,
        public readonly EnvironmentType $environmentType,
        public readonly array $missingKeys,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Build an exception from a list of missing required keys.
     * Produces an actionable message naming each missing key and how to set it.
     *
     * @param  array<int, string>  $missingKeys
     */
    public static function fromMissingKeys(
        EnvironmentType $type,
        array $missingKeys,
    ): self {
        $lines = [];
        foreach ($missingKeys as $key) {
            $lines[] = self::actionableLine($key);
        }

        $count = count($missingKeys);
        $message = sprintf(
            "Environment validation failed for %s (%d missing required %s):\n  - %s",
            $type->label(),
            $count,
            $count === 1 ? 'key' : 'keys',
            implode("\n  - ", $lines),
        );

        return new self($message, $type, array_values($missingKeys));
    }

    /**
     * Render a single "what to set, with the command to set it" line.
     */
    private static function actionableLine(string $key): string
    {
        $hint = match ($key) {
            'APP_KEY'        => 'run: php artisan key:generate',
            'APP_URL'        => 'set in .env: APP_URL=https://your-domain.test',
            'DB_CONNECTION'  => 'set in .env: DB_CONNECTION=pgsql (or DB_CONNECTION=neon)',
            'DB_HOST'        => 'set in .env: DB_HOST=127.0.0.1 (or provide DATABASE_URL)',
            'DATABASE_URL'   => 'set in .env: DATABASE_URL=postgresql://user:pass@host/db?sslmode=require',
            'CACHE_STORE'    => 'set in .env: CACHE_STORE=file (or redis, array, ...) ',
            'QUEUE_CONNECTION' => 'set in .env: QUEUE_CONNECTION=sync (or redis, database, ...) ',
            'REDIS_HOST'     => 'set in .env: REDIS_HOST=127.0.0.1 (or provide REDIS_URL)',
            'REDIS_URL'      => 'set in .env: REDIS_URL=redis://user:pass@host:6379/0',
            'NEON_BRANCH'    => 'set in .env: NEON_BRANCH=main (or feature branch name)',
            'NEON_ROLE'      => 'set in .env: NEON_ROLE=app (or owner, reader, ...) ',
            'RAZORPAY_KEY_ID'     => 'set in .env: RAZORPAY_KEY_ID=rzp_test_... or rzp_live_...',
            'RAZORPAY_KEY_SECRET' => 'set in .env: RAZORPAY_KEY_SECRET=... (do not commit)',
            'RAZORPAY_WEBHOOK_SECRET' => 'set in .env: RAZORPAY_WEBHOOK_SECRET=...',
            'PAYPAL_CLIENT_ID'     => 'set in .env: PAYPAL_CLIENT_ID=...',
            'PAYPAL_CLIENT_SECRET' => 'set in .env: PAYPAL_CLIENT_SECRET=... (do not commit)',
            default          => "set in .env: {$key}=...",
        };

        return "{$key}  →  {$hint}";
    }
}