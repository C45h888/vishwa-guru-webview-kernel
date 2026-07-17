<?php

declare(strict_types=1);

namespace App\Persistence\Infrastructure;

use App\Persistence\Contracts\RepositoryRegistryContract;
use App\Shared\Contracts\RepositoryContract;
use App\Shared\ValueObjects\Identifier;

/**
 * In-memory repository registry — maps entity type strings to
 * repository class names.
 *
 * Repositories register themselves at boot via PaymentsServiceProvider
 * (and any future module provider). Services look up repositories by
 * entity type via the kernel contract — never by class name —
 * which lets implementations be swapped without touching the consumer.
 *
 * Singleton-scoped (registered in PersistenceServiceProvider). The
 * identifier() method exposes a stable diagnostic handle so callers
 * can tell registries apart in logs and tests.
 *
 * Threading model: PHP single-threaded per request. No locking needed.
 */
final class RepositoryRegistry implements RepositoryRegistryContract
{
    /**
     * @var array<string, class-string<RepositoryContract>>
     */
    private array $registrations = [];

    /**
     * @param  class-string<RepositoryContract>  $repositoryClass
     */
    public function register(string $entityType, string $repositoryClass): void
    {
        $this->registrations[$entityType] = $repositoryClass;
    }

    /**
     * @return class-string<RepositoryContract>|null
     */
    public function resolve(string $entityType): ?string
    {
        return $this->registrations[$entityType] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function entityTypes(): array
    {
        return array_keys($this->registrations);
    }

    public function has(string $entityType): bool
    {
        return isset($this->registrations[$entityType]);
    }

    public function identifier(): Identifier
    {
        return Identifier::generate();
    }
}