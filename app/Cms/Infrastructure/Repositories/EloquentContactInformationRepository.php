<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Repositories;

use App\Cms\Domain\Entities\ContactInformation;
use App\Cms\Domain\Repositories\ContactInformationRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use RuntimeException;

/**
 * Eloquent implementation of ContactInformationRepositoryContract.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §5.1
 *
 * @implements ContactInformationRepositoryContract
 */
final class EloquentContactInformationRepository implements ContactInformationRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    public function findById(EntityId $id): ?ContactInformation
    {
        $result = $this->adapter->query(
            'SELECT * FROM contact_information WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return ContactInformation::fromRow($result->value()[0]);
    }

    public function listAll(?int $limit = null): array
    {
        $sql = 'SELECT * FROM contact_information
                WHERE deleted_at IS NULL
                ORDER BY contact_type, display_order ASC';
        $params = [];
        if ($limit !== null) {
            $sql .= ' LIMIT :lim';
            $params['lim'] = $limit;
        }

        return $this->fetchList($sql, $params);
    }

    public function listByType(string $contactType): array
    {
        return $this->fetchList(
            'SELECT * FROM contact_information
             WHERE contact_type = :type AND deleted_at IS NULL
             ORDER BY display_order ASC',
            ['type' => $contactType],
        );
    }

    public function listPrimary(): array
    {
        return $this->fetchList(
            'SELECT * FROM contact_information
             WHERE is_primary = TRUE AND deleted_at IS NULL',
            [],
        );
    }

    public function save(ContactInformation $contact): void
    {
        $row = $contact->toArray();
        $exec = $this->adapter->execute(
            'INSERT INTO contact_information (
                id, label, contact_type, value, is_primary, display_order,
                metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :label, :ctype, :value, :primary, :dorder,
                :meta, :created, :updated, :deleted
            )',
            [
                'id' => $row['id'],
                'label' => $row['label'],
                'ctype' => $row['contact_type'],
                'value' => $row['value'],
                'primary' => $row['is_primary'],
                'dorder' => $row['display_order'],
                'meta' => $row['metadata'],
                'created' => $row['created_at'],
                'updated' => $row['updated_at'],
                'deleted' => $row['deleted_at'],
            ],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentContactInformationRepository::save failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    public function update(ContactInformation $contact): void
    {
        $row = $contact->toArray();
        $exec = $this->adapter->execute(
            'UPDATE contact_information SET
                label = :label,
                contact_type = :ctype,
                value = :value,
                is_primary = :primary,
                display_order = :dorder,
                metadata = :meta,
                updated_at = :updated,
                deleted_at = :deleted
            WHERE id = :id',
            [
                'id' => $row['id'],
                'label' => $row['label'],
                'ctype' => $row['contact_type'],
                'value' => $row['value'],
                'primary' => $row['is_primary'],
                'dorder' => $row['display_order'],
                'meta' => $row['metadata'],
                'updated' => $row['updated_at'],
                'deleted' => $row['deleted_at'],
            ],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentContactInformationRepository::update failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    public function softDelete(EntityId $id): void
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $exec = $this->adapter->execute(
            'UPDATE contact_information SET deleted_at = :now WHERE id = :id',
            ['id' => $id->value(), 'now' => $now],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentContactInformationRepository::softDelete failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<ContactInformation>
     */
    private function fetchList(string $sql, array $params): array
    {
        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row) => ContactInformation::fromRow($row),
            $result->value(),
        );
    }
}