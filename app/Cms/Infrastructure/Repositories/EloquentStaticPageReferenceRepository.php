<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Repositories;

use App\Cms\Domain\Entities\StaticPageReference;
use App\Cms\Domain\Enums\PageReferenceType;
use App\Cms\Domain\Repositories\StaticPageReferenceRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use RuntimeException;

/**
 * Eloquent implementation of StaticPageReferenceRepositoryContract.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §5.1
 *
 * @implements StaticPageReferenceRepositoryContract
 */
final class EloquentStaticPageReferenceRepository implements StaticPageReferenceRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    public function listForPage(EntityId $pageId): array
    {
        return $this->fetchList(
            'SELECT * FROM static_page_references
             WHERE static_page_id = :pid
             ORDER BY display_order ASC',
            ['pid' => $pageId->value()],
        );
    }

    public function listByReference(PageReferenceType $type, EntityId $referenceId): array
    {
        return $this->fetchList(
            'SELECT * FROM static_page_references
             WHERE reference_type = :type AND reference_id = :rid',
            ['type' => $type->value, 'rid' => $referenceId->value()],
        );
    }

    public function existsForPage(
        EntityId $pageId,
        PageReferenceType $type,
        EntityId $referenceId,
        ?string $context = null,
    ): bool {
        // Doctrine: NULL-safe context comparison is Postgres-native via
        // `IS NOT DISTINCT FROM`, but the Laravel Schema Builder does
        // not surface it portably, so the SQLite path uses an explicit
        // `(context IS NULL AND :ctx IS NULL) OR context = :ctx` rewrite.
        // This matters because `static_page_references` has a UNIQUE
        // partial index on (static_page_id, reference_type, reference_id,
        // context); a wrong read here lets duplicates through.
        if ($this->adapter->driver() === 'pgsql') {
            $sql = 'SELECT 1 FROM static_page_references
                    WHERE static_page_id = :pid
                      AND reference_type = :type
                      AND reference_id = :rid
                      AND context IS NOT DISTINCT FROM :ctx
                    LIMIT 1';
            $params = [
                'pid' => $pageId->value(),
                'type' => $type->value,
                'rid' => $referenceId->value(),
                'ctx' => $context,
            ];
        } else {
            $sql = 'SELECT 1 FROM static_page_references
                    WHERE static_page_id = :pid
                      AND reference_type = :type
                      AND reference_id = :rid
                      AND ((context IS NULL AND :ctx IS NULL) OR context = :ctx)
                    LIMIT 1';
            $params = [
                'pid' => $pageId->value(),
                'type' => $type->value,
                'rid' => $referenceId->value(),
                'ctx' => $context,
            ];
        }

        $result = $this->adapter->query($sql, $params);

        return ! $result->isFailure() && ! empty($result->value());
    }

    public function attach(StaticPageReference $reference): void
    {
        $row = $reference->toArray();
        $exec = $this->adapter->execute(
            'INSERT INTO static_page_references (
                id, static_page_id, reference_type, reference_id,
                display_order, context, created_at
            ) VALUES (
                :id, :pid, :rtype, :rid, :dorder, :ctx, :created
            )',
            [
                'id' => $row['id'],
                'pid' => $row['static_page_id'],
                'rtype' => $row['reference_type'],
                'rid' => $row['reference_id'],
                'dorder' => $row['display_order'],
                'ctx' => $row['context'],
                'created' => $row['created_at'],
            ],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentStaticPageReferenceRepository::attach failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    public function detach(EntityId $referenceRowId): void
    {
        $exec = $this->adapter->execute(
            'DELETE FROM static_page_references WHERE id = :id',
            ['id' => $referenceRowId->value()],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentStaticPageReferenceRepository::detach failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    public function detachAllForPage(EntityId $pageId): void
    {
        $exec = $this->adapter->execute(
            'DELETE FROM static_page_references WHERE static_page_id = :pid',
            ['pid' => $pageId->value()],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentStaticPageReferenceRepository::detachAllForPage failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<StaticPageReference>
     */
    private function fetchList(string $sql, array $params): array
    {
        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row) => StaticPageReference::fromRow($row),
            $result->value(),
        );
    }
}