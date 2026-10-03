<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Persistence;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Shared\Support\Clock;
use App\Shared\Support\IdentifierGenerator;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use PDO;
use PDOException;

/**
 * In-memory PDO-backed adapter used for integration tests.
 *
 * Wraps PDO with the sqlite :memory: driver and loads the canonical
 * V1 schema from `schema-neon/V1-schema.sql` on first connect. This
 * is the same surface as PostgresAdapter but without sslmode and
 * PostgreSQL-specific type coercions.
 *
 * Concurrency model: this adapter is single-connection by design
 * (PDO sqlite memory + the transaction() callback that releases
 * the implicit transaction lock). Tests that need to observe
 * concurrent transactions should NOT use this adapter — they
 * should mock the contract.
 *
 * Schema loading: the schema file uses PostgreSQL-flavoured types
 * (TIMESTAMPTZ, JSONB, ENUM). SQLite does not recognise those
 * natively, so the loader applies type-rewrites:
 *   - TIMESTAMPTZ   → TEXT  (round-tripped via DateTimeImmutable)
 *   - JSONB         → TEXT  (round-tripped via json_encode/decode)
 *   - ENUM types    → TEXT CHECK (preserved)
 *   - UUID          → TEXT  (ULIDs are stored as TEXT)
 *   - BOOLEAN       → INTEGER (0/1)
 *   - partial indexes with NOWAIT → dropped (SQLite limitation)
 *
 * The rewrites preserve the test surface (column names, table
 * structure, CHECK constraints) so repository tests verify the
 * application logic, not PostgreSQL-specific behaviour.
 */
final class InMemoryAdapter implements PersistenceAdapterContract
{
    private ?PDO $pdo = null;

    private bool $schemaLoaded = false;

    public function __construct(
        private readonly Clock $clock,
        private readonly IdentifierGenerator $ids,
        private readonly string $schemaPath,
    ) {}

    public function connect(): Result
    {
        try {
            $this->pdo = new PDO('sqlite::memory:');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON');

            return Result::success(null);
        } catch (PDOException $e) {
            return Result::failure('pdo_connect_failed: '.$e->getMessage());
        }
    }

    public function disconnect(): Result
    {
        $this->pdo = null;
        $this->schemaLoaded = false;

        return Result::success(null);
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return Result<T>
     */
    public function transaction(callable $callback): Result
    {
        if ($this->pdo === null) {
            $connect = $this->connect();
            if ($connect->isFailure()) {
                return $connect;
            }
        }

        if (! $this->schemaLoaded) {
            $loaded = $this->loadSchema();
            if ($loaded->isFailure()) {
                return $loaded;
            }
        }

        try {
            $this->pdo->beginTransaction();
            $value = $callback();
            $this->pdo->commit();

            // Pass a callback-returned Result through instead of wrapping it
            // again (nested Results break post-commit domain calls — see
            // LaravelDbAdapter::transaction for the 2026-10-03 incident note).
            return $value instanceof Result
                ? $value
                : Result::success($value);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return Result::failure('transaction_rolled_back: '.$e->getMessage());
        }
    }

    public function driver(): string
    {
        return 'sqlite';
    }

    public function identifier(): Identifier
    {
        return new Identifier($this->ids->next());
    }

    /**
     * Type-surface reflection for the in-memory SQLite adapter.
     *
     * Mirrors LaravelDbAdapter::connectionMetadata() but without a
     * configured host/port (in-memory SQLite has none). Used by tests
     * to verify the adapter binding shape.
     *
     * @return Result<array<string, mixed>>
     */
    public function connectionMetadata(): Result
    {
        try {
            return Result::success([
                'driver'           => 'sqlite',
                'identifier'       => (string) $this->identifier(),
                'is_connected'     => $this->pdo !== null,
                'database'         => ':memory:',
                'host'             => null,
                'port'             => null,
                'username'         => null,
                'application_name' => 'temple-trust-tests',
                'sslmode'          => null,
            ]);
        } catch (\Throwable $e) {
            return Result::failure('connection_metadata_failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return Result<array<int, array<string, mixed>>>
     */
    public function query(string $sql, array $params = []): Result
    {
        if ($this->pdo === null) {
            $connect = $this->connect();
            if ($connect->isFailure()) {
                return $connect;
            }
        }
        if (! $this->schemaLoaded) {
            $loaded = $this->loadSchema();
            if ($loaded->isFailure()) {
                return $loaded;
            }
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return Result::success($stmt->fetchAll());
        } catch (PDOException $e) {
            return Result::failure('query_failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return Result<int>
     */
    public function execute(string $sql, array $params = []): Result
    {
        if ($this->pdo === null) {
            $connect = $this->connect();
            if ($connect->isFailure()) {
                return $connect;
            }
        }
        if (! $this->schemaLoaded) {
            $loaded = $this->loadSchema();
            if ($loaded->isFailure()) {
                return $loaded;
            }
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindParams($stmt, $params);
            $stmt->execute();

            return Result::success($stmt->rowCount());
        } catch (PDOException $e) {
            return Result::failure('execute_failed: '.$e->getMessage());
        }
    }

    /**
     * Apply PostgreSQL→SQLite type rewrites and load the schema.
     *
     * @return Result<void>
     */
    private function loadSchema(): Result
    {
        if (! is_readable($this->schemaPath)) {
            return Result::failure(
                'schema_not_readable: '.$this->schemaPath,
            );
        }

        $raw = file_get_contents($this->schemaPath);
        if ($raw === false) {
            return Result::failure('schema_read_failed: '.$this->schemaPath);
        }

        $rewritten = $this->rewriteForSqlite($raw);

        try {
            // SQLite's exec() runs multi-statement SQL only if no
            // prepared-statement protocol is involved. We split on
            // semicolons and execute each statement separately so
            // triggers/functions (which sqlite doesn't support) are
            // skipped instead of breaking the load.
            foreach ($this->splitStatements($rewritten) as $statement) {
                $this->pdo->exec($statement);
            }
            $this->schemaLoaded = true;

            return Result::success(null);
        } catch (PDOException $e) {
            return Result::failure('schema_load_failed: '.$e->getMessage());
        }
    }

    /**
     * Rewrite PostgreSQL-specific DDL for SQLite.
     */
    private function rewriteForSqlite(string $sql): string
    {
        $rewrites = [
            // CREATE EXTENSION (PostgreSQL-only) → drop clause
            '/CREATE\s+EXTENSION[^;]*;/i' => '/* CREATE EXTENSION stripped for sqlite */',

            // CREATE TYPE ... AS ENUM → drop clause (sqlite stores as TEXT)
            '/CREATE\s+TYPE\s+\w+\s+AS\s+ENUM\s*\([^)]+\)\s*;/i'
                => '/* CREATE TYPE ENUM stripped for sqlite */',

            // CREATE FUNCTION ... LANGUAGE plpgsql → drop the whole body
            '/CREATE\s+(OR\s+REPLACE\s+)?FUNCTION[\s\S]*?LANGUAGE\s+\w+;/i'
                => '/* CREATE FUNCTION stripped for sqlite */',

            // CREATE TRIGGER → drop (sqlite triggers differ in syntax)
            '/CREATE\s+(OR\s+REPLACE\s+)?(CONSTRAINT\s+)?TRIGGER[\s\S]*?;/i'
                => '/* CREATE TRIGGER stripped for sqlite */',

            // COMMENT ON [TABLE|COLUMN|EXTENSION] ... IS '...' → drop
            // (PostgreSQL stores these in pg_description; sqlite has no
            // equivalent). The regex must NOT use the `[\s\S]*?;`
            // form because COMMENT descriptions frequently contain
            // `;` inside the string literal. Use a non-capturing
            // group over the description and anchor on `';`.
            "/COMMENT\\s+ON\\s+(?:TABLE|COLUMN|EXTENSION|SCHEMA|INDEX|CONSTRAINT|SEQUENCE|VIEW|FUNCTION|TRIGGER|TYPE|RULE|POLICY|EVENT\\s+TRIGGER)\\s+\\w+(?:\\.\\w+)*\\s+IS\\s+'(?:[^']|'')*'\\s*;/i"
                => '/* COMMENT ON stripped for sqlite */',

            // PostgreSQL array types: CHAR(3)[] → TEXT, INT[] → TEXT, etc.
            // The pattern uses [^\[\]] instead of [^\)] for the
            // inner group so the regex cannot greedily consume
            // table-body parens when looking for the trailing [].
            '/\b\w+\s*\([^()\[\]]*\)\s*\[\s*\]|\b\w+\s*\[\s*\]/i' => 'TEXT',

            // TIMESTAMPTZ → TEXT (DateTimeImmutable ISO-8601 round-trip)
            '/\bTIMESTAMPTZ\b/i' => 'TEXT',

            // JSONB → TEXT (json_encode/decode round-trip)
            '/\bJSONB\b/i' => 'TEXT',

            // BOOLEAN → INTEGER (SQLite uses INTEGER 0/1)
            '/\bBOOLEAN\b/i' => 'INTEGER',

            // UUID → TEXT (ULIDs are 26-char Crockford strings)
            '/\bUUID\b/i' => 'TEXT',

            // GENERATED ALWAYS AS IDENTITY → drop clause
            '/GENERATED\s+ALWAYS\s+AS\s+IDENTITY/i' => '',

            // Partial indexes → comment-out (SQLite supports partial
            // indexes but the helper functions in the WHERE clauses
            // aren't available).
            '/CREATE\s+(UNIQUE\s+)?INDEX\s+\w+\s+ON\s+(\w+)\s*\([^)]*\)\s*WHERE[^;]+;/i'
                => '/* CREATE PARTIAL INDEX stripped for sqlite */',
        ];

        return preg_replace(array_keys($rewrites), array_values($rewrites), $sql) ?? $sql;
    }

    /**
     * Split a SQL script into individual statements on top-level
     * semicolons. Skips empty statements and SQL comments that
     * occupy a full line.
     *
     * @return array<int, string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $lines = explode("\n", $sql);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }
            $current .= $line."\n";
            if (str_ends_with($trimmed, ';')) {
                $candidate = trim($current);
                if ($candidate !== '' && $candidate !== ';') {
                    $statements[] = $candidate;
                }
                $current = '';
            }
        }
        $tail = trim($current);
        if ($tail !== '' && $tail !== ';') {
            $statements[] = $tail;
        }

        return $statements;
    }

    /**
     * Bind positional parameters to a PDO statement. PDO requires
     * positional parameters as 1-indexed arrays, so we map the
     * associative array in order.
     *
     * @param  array<string, scalar|null>  $params
     */
    private function bindParams(\PDOStatement $stmt, array $params): void
    {
        $position = 1;
        foreach ($params as $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($position, $value, $type);
            $position++;
        }
    }
}