<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Represents the outcome of an operation that can succeed or fail.
 * Used throughout services to return explicit success/failure without throwing.
 *
 * @template T
 */
final class Result
{
    /**
     * @template U
     *
     * @param  U  $value
     * @phpstan-return self<U>
     * @return Result<U>
     */
    public static function success(mixed $value): Result
    {
        /** @var Result<U> $result */
        $result = new Result(true, $value, null);

        return $result;
    }

    /**
     * @template U
     *
     * @param  U|null  $value
     * @phpstan-return self<U>
     * @return Result<U>
     */
    public static function failure(string $error, mixed $value = null): Result
    {
        /** @var Result<U> $result */
        $result = new Result(false, $value, $error);

        return $result;
    }

    private function __construct(
        private readonly bool $ok,
        private readonly mixed $value,
        private readonly ?string $error
    ) {}

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function isFailure(): bool
    {
        return ! $this->ok;
    }

    /**
     * @return T
     */
    public function value(): mixed
    {
        return $this->value;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    /**
     * @template U
     *
     * @param  U  $default
     * @return T|U
     */
    public function valueOr(mixed $default): mixed
    {
        return $this->ok ? $this->value : $default;
    }

    /**
     * @template U
     *
     * @param  callable(T): U  $map
     * @return Result<U>
     */
    public function map(callable $map): Result
    {
        if ($this->ok) {
            /** @var U $mapped */
            $mapped = $map($this->value);
            /** @var Result<U> $result */
            $result = Result::success($mapped);

            return $result;
        }
        /** @var Result<U> $result */
        $result = Result::failure($this->error ?? 'Unknown error');

        return $result;
    }
}
