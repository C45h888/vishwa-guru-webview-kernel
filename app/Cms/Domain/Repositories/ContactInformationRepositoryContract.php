<?php

declare(strict_types=1);

namespace App\Cms\Domain\Repositories;

use App\Cms\Domain\Entities\ContactInformation;
use App\Persistence\ValueObjects\EntityId;

/**
 * Persistence boundary for ContactInformation.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §3.5.4
 */
interface ContactInformationRepositoryContract
{
    public function findById(EntityId $id): ?ContactInformation;

    /**
     * @return list<ContactInformation>
     */
    public function listAll(?int $limit = null): array;

    /**
     * @return list<ContactInformation>
     */
    public function listByType(string $contactType): array;

    /**
     * @return list<ContactInformation>
     */
    public function listPrimary(): array;

    public function save(ContactInformation $contact): void;

    public function update(ContactInformation $contact): void;

    public function softDelete(EntityId $id): void;
}