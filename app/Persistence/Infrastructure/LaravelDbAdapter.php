<?php

declare(strict_types=1);

namespace App\Persistence\Infrastructure;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Database\ConnectionInterface;

/**
 * Laravel DB adapter — single implementation for both production and tests.
 *
 * Production: Laravel resolves the "pgsql" connection from config/database.php,
 * which points at the Neon PostgreSQL branch.
 *
 * Tests: phpunit.xml sets DB_CONNECTION=sqlite + DB_DATABASE=:memory:,
 * so Laravel resolves the "sqlite" connection. The RefreshDatabase trait
 * applies the SQLite migration schema before each test.
 *
 * This adapter does NOT call DB:: (the static facade) — it receives
 * an injected ConnectionInterface, making it testable and framework-agnostic
 * at the contract boundary.
 */
final class LaravelDbAdapter implements PersistenceAdapterContract
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    /**
     * Laravel connections are lazily established; nothing to pre-connect.
     *
     * @return Result<void>
     */
    public function connect(): Result
    {
        return Result::success(null);
    }

    /**
     * @return Result<void>
     */
    public function disconnect(): Result
    {
        $this->connection->disconnect();

        return Result::success(null);
    }

    public function isConnected(): bool
    {
        try {
            return $this->connection->getDatabaseName() !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return Result<T>
     */
    public function transaction(callable $callback): Result
    {
        try {
            $value = $this->connection->transaction($callback);

            return Result::success($value);
        } catch (\Throwable $e) {
            return Result::failure('transaction_failed: '.$e->getMessage());
        }
    }

    /**
     * Execute a SELECT and return all rows as associative arrays.
     *
     * Laravel's select() returns array<int, object>. We cast each row
     * to array<string, mixed> so entity ::fromRow() callers get the
     * shape they expect.
     *
     * @param  array<string, scalar|null>  $params
     * @return Result<array<int, array<string, mixed>>>
     */
    public function query(string $sql, array $params = []): Result
    {
        try {
            $rows = $this->connection->select($sql, $params);

            return Result::success(array_map(
                static fn (object $row): array => (array) $row,
                $rows,
            ));
        } catch (\Throwable $e) {
            return Result::failure('query_failed: '.$e->getMessage());
        }
    }

    /**
     * Execute a write statement (INSERT/UPDATE/DELETE) and return affected rows.
     *
     * @param  array<string, scalar|null>  $params
     * @return Result<int>
     */
    public function execute(string $sql, array $params = []): Result
    {
        try {
            $affected = $this->connection->affectingStatement($sql, $params);

            return Result::success($affected);
        } catch (\Throwable $e) {
            return Result::failure('execute_failed: '.$e->getMessage());
        }
    }

    public function driver(): string
    {
        return $this->connection->getDriverName();
    }

    public function identifier(): Identifier
    {
        return new Identifier('laravel-db');
    }
}
