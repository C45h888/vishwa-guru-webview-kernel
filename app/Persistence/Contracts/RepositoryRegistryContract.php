<?php

declare(strict_types=1);

namespace App\Persistence\Contracts;

use App\Shared\ValueObjects\Identifier;

/**
 * Maps entity types to their repository implementations.
 *
 * Repositories register themselves with the registry at boot time.
 * Services request repositories by entity type, never by class name —
 * this lets implementations be swapped without code changes.
 */
interface RepositoryRegistryContract
{
    /**
     * Register a repository for an entity type.
     *
     * @param string $entityType
     * @param class-string<RepositoryContract> $repositoryClass
     */
    public function register(string $entityType, string $repositoryClass): void;

    /**
     * Resolve the repository class for an entity type.
     *
     * @param string $entityType
     * @return class-string<RepositoryContract>|null
     */
    public function resolve(string $entityType): ?string;

    /**
     * List all registered entity types.
     *
     * @return array<int, string>
     */
    public function entityTypes(): array;

    /**
     * Whether a repository is registered for the given entity type.
     */
    public function has(string $entityType): bool;

    /**
     * Get a registry identifier for diagnostic purposes.
     */
    public function identifier(): Identifier;
}