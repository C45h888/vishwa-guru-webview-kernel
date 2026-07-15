<?php

declare(strict_types=1);

namespace App\Payments\Domain\Enums;

/**
 * Classification of a payment failure.
 *
 * The string values MUST match the `failure_classification` PostgreSQL
 * enum defined in `schema-neon/V1-schema.sql`. The FailureStateManager
 * is the only component that may assign a classification to a failure;
 * downstream code MUST read it from the persisted FailureState entity.
 *
 * Recoverable classifications support automatic retry up to
 * failure_states.max_retries. Terminal classifications require manual
 * operator resolution and are surfaced to the admin console.
 */
enum FailureClassification: string
{
    case RECOVERABLE_TRANSIENT = 'recoverable_transient';
    case RECOVERABLE_TERMINAL = 'recoverable_terminal';
    case TERMINAL_INVALID = 'terminal_invalid';
    case TERMINAL_FRAUD = 'terminal_fraud';

    /**
     * Whether this classification permits automatic retry.
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::RECOVERABLE_TRANSIENT, self::RECOVERABLE_TERMINAL => true,
            self::TERMINAL_INVALID, self::TERMINAL_FRAUD => false,
        };
    }

    /**
     * Whether this classification is terminal (requires manual resolution).
     */
    public function isTerminal(): bool
    {
        return ! $this->isRetryable();
    }

    /**
     * Default maximum retry count for this classification.
     * Operators may override per-row via failure_states.max_retries.
     */
    public function defaultMaxRetries(): int
    {
        return match ($this) {
            self::RECOVERABLE_TRANSIENT => 3,
            self::RECOVERABLE_TERMINAL => 5,
            self::TERMINAL_INVALID, self::TERMINAL_FRAUD => 0,
        };
    }

    /**
     * Default backoff window in seconds between retry attempts.
     * Operators may override per-row via failure_states.next_retry_at.
     */
    public function defaultBackoffSeconds(): int
    {
        return match ($this) {
            self::RECOVERABLE_TRANSIENT => 60,
            self::RECOVERABLE_TERMINAL => 300,
            self::TERMINAL_INVALID, self::TERMINAL_FRAUD => 0,
        };
    }

    /**
     * Severity rank for operator dashboards. Higher = more urgent.
     */
    public function severity(): int
    {
        return match ($this) {
            self::TERMINAL_FRAUD => 100,
            self::TERMINAL_INVALID => 80,
            self::RECOVERABLE_TERMINAL => 40,
            self::RECOVERABLE_TRANSIENT => 10,
        };
    }

    /**
     * Human-readable label for operator-facing surfaces.
     */
    public function label(): string
    {
        return match ($this) {
            self::RECOVERABLE_TRANSIENT => 'Recoverable — Transient',
            self::RECOVERABLE_TERMINAL => 'Recoverable — Terminal Retry',
            self::TERMINAL_INVALID => 'Terminal — Invalid',
            self::TERMINAL_FRAUD => 'Terminal — Fraud',
        };
    }
}