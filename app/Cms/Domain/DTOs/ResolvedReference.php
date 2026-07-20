<?php

declare(strict_types=1);

namespace App\Cms\Domain\DTOs;

use App\Cms\Domain\Enums\PageReferenceType;
use App\Cms\Domain\Enums\ReferenceStatus;
use App\Persistence\ValueObjects\EntityId;

/**
 * A static page reference with its resolution outcome.
 *
 * For resolved CAMPAIGN references, `payload` is a CampaignSummary VO
 * from the Payments kernel. For unresolved references (missing kernel,
 * inactive target), `payload` is null and `status` is UNRESOLVED.
 */
final readonly class ResolvedReference
{
    /**
     * @param  mixed  $payload  CampaignSummary for CAMPAIGN; null otherwise
     */
    public function __construct(
        public PageReferenceType $referenceType,
        public EntityId $referenceId,
        public ?string $context,
        public int $displayOrder,
        public ReferenceStatus $status,
        public mixed $payload,
    ) {
    }
}