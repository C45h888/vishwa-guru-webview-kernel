<?php

declare(strict_types=1);

namespace App\Cms\Domain\Repositories;

use App\Cms\Domain\Entities\StaticPageReference;
use App\Cms\Domain\Enums\PageReferenceType;
use App\Persistence\ValueObjects\EntityId;

/**
 * Persistence boundary for StaticPageReference junction rows.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §3.5.3
 */
interface StaticPageReferenceRepositoryContract
{
    /**
     * @return list<StaticPageReference>
     */
    public function listForPage(EntityId $pageId): array;

    /**
     * @return list<StaticPageReference>
     */
    public function listByReference(PageReferenceType $type, EntityId $referenceId): array;

    public function existsForPage(
        EntityId $pageId,
        PageReferenceType $type,
        EntityId $referenceId,
        ?string $context = null,
    ): bool;

    public function attach(StaticPageReference $reference): void;

    public function detach(EntityId $referenceRowId): void;

    public function detachAllForPage(EntityId $pageId): void;
}