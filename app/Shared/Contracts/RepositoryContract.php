<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Base contract for all repository implementations.
 * Repositories own all persistence operations.
 */
interface RepositoryContract
{
    /**
     * Find an entity by its primary identifier.
     */
    public function find(int|string $id): ?object;

    /**
     * Get all entities of this type.
     *
     * @return array<int, object>
     */
    public function all(): array;

    /**
     * Create a new entity from the given data.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): object;

    /**
     * Update an existing entity.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int|string $id, array $data): object;

    /**
     * Delete an entity by its primary identifier.
     */
    public function delete(int|string $id): bool;

    /**
     * Check whether an entity exists.
     */
    public function exists(int|string $id): bool;
}
