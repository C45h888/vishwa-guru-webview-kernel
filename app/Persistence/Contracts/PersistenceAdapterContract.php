<?php

declare(strict_types=1);

namespace App\Persistence\Contracts;

use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * Persistence adapter abstracts the underlying storage layer.
 *
 * Phase 0.25: contract only.
 * Phase 1 (Database Architecture): PostgreSQL adapter implementation.
 *
 * The adapter provides a uniform interface for:
 *   - connection lifecycle
 *   - transaction control
 *   - query execution
 *
 * Repositories use this — they don't talk to PDO or Eloquent directly.
 */
interface PersistenceAdapterContract
{
    /**
     * Connect to the persistence backend.
     *
     * @return Result<void>
     */
    public function connect(): Result;

    /**
     * Disconnect from the persistence backend.
     *
     * @return Result<void>
     */
    public function disconnect(): Result;

    /**
     * Whether the adapter is currently connected.
     */
    public function isConnected(): bool;

    /**
     * Run a callback inside a transaction.
     * Commits on success, rolls back on throw.
     *
     * @template T
     * @param callable(): T $callback
     * @return Result<T>
     */
    public function transaction(callable $callback): Result;

    /**
     * The driver identifier (e.g. "pgsql", "mysql", "sqlite").
     */
    public function driver(): string;

    /**
     * The adapter identifier (unique per registry).
     */
    public function identifier(): Identifier;
}