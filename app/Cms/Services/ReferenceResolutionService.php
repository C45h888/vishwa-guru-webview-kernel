<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Domain\DTOs\ResolvedReference;
use App\Cms\Domain\Entities\StaticPageReference;
use App\Cms\Domain\Enums\PageReferenceType;
use App\Cms\Domain\Enums\ReferenceStatus;
use App\Payments\Contracts\CampaignQueryContract;
use App\Shared\Support\Clock;
use Psr\Log\LoggerInterface;

/**
 * ReferenceResolutionService — validates + fetches cross-kernel references.
 *
 * Used by StaticPageRendererService. The ONLY place in the CMS kernel
 * that knows about cross-kernel contracts.
 *
 * Resolution order (cheap-first):
 *   CAMPAIGN → isDisplayable (boolean check) → findActiveById (full payload)
 *   GALLERY_IMAGE / EVENT → unresolved immediately (no kernel yet)
 *
 * Never throws — returns ResolvedReference with status = Unresolved on
 * missing kernel or invalid target. Logs warnings for unresolved refs
 * so admins can detect broken references via log inspection.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §4.6
 */
final class ReferenceResolutionService
{
    public function __construct(
        private readonly CampaignQueryContract $campaigns,
        private readonly Clock $clock,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param  list<StaticPageReference>  $references
     * @return list<ResolvedReference>
     */
    public function resolveMany(array $references): array
    {
        $out = [];
        foreach ($references as $reference) {
            $out[] = $this->resolve($reference);
        }

        return $out;
    }

    public function resolve(StaticPageReference $reference): ResolvedReference
    {
        return match ($reference->referenceType()) {
            PageReferenceType::CAMPAIGN => $this->resolveCampaign($reference),
            PageReferenceType::GALLERY_IMAGE => $this->unresolvedWithLog(
                $reference,
                'gallery kernel not yet implemented'
            ),
            PageReferenceType::EVENT => $this->unresolvedWithLog(
                $reference,
                'events kernel not yet implemented'
            ),
        };
    }

    private function resolveCampaign(StaticPageReference $reference): ResolvedReference
    {
        // Cheap existence check first
        if (! $this->campaigns->isDisplayable($reference->referenceId())) {
            return $this->unresolvedWithLog($reference, 'campaign not displayable');
        }

        // Full payload fetch
        $campaign = $this->campaigns->findActiveById($reference->referenceId());
        if ($campaign === null) {
            return $this->unresolvedWithLog($reference, 'campaign lookup returned null');
        }

        return new ResolvedReference(
            referenceType: $reference->referenceType(),
            referenceId: $reference->referenceId(),
            context: $reference->context(),
            displayOrder: $reference->displayOrder(),
            status: ReferenceStatus::RESOLVED,
            payload: $campaign,
        );
    }

    private function unresolvedWithLog(StaticPageReference $reference, string $reason): ResolvedReference
    {
        if ($this->logger !== null) {
            $this->logger->warning('Cms unresolved reference', [
                'reference_id' => $reference->id()->value(),
                'reference_type' => $reference->referenceType()->value,
                'target_id' => $reference->referenceId()->value(),
                'reason' => $reason,
            ]);
        }

        return new ResolvedReference(
            referenceType: $reference->referenceType(),
            referenceId: $reference->referenceId(),
            context: $reference->context(),
            displayOrder: $reference->displayOrder(),
            status: ReferenceStatus::UNRESOLVED,
            payload: null,
        );
    }
}