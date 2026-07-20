<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Domain\Entities\ContactInformation;
use App\Cms\Domain\Repositories\ContactInformationRepositoryContract;
use App\Persistence\ValueObjects\EntityId;

/**
 * ContactInformationService — read-only service in V1.
 *
 * Phase 4 admin UI adds mutation methods. V1 returns queries only;
 * contact points are seeded by migration / admin SQL.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §4.5
 */
final class ContactInformationService
{
    public function __construct(
        private readonly ContactInformationRepositoryContract $contacts,
    ) {
    }

    /**
     * @return list<ContactInformation>
     */
    public function listAll(): array
    {
        return $this->contacts->listAll();
    }

    /**
     * @return list<ContactInformation>
     */
    public function listByType(string $type): array
    {
        return $this->contacts->listByType($type);
    }

    /**
     * @return list<ContactInformation>
     */
    public function listPrimary(): array
    {
        return $this->contacts->listPrimary();
    }

    public function findById(EntityId $id): ?ContactInformation
    {
        return $this->contacts->findById($id);
    }
}