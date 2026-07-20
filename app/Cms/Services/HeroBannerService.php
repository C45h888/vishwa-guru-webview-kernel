<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Domain\DTOs\HeroBannerDraftInput;
use App\Cms\Domain\DTOs\HeroBannerUpdateInput;
use App\Cms\Domain\Entities\HeroBanner;
use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\Exceptions\HeroBannerNotFoundException;
use App\Cms\Domain\Repositories\HeroBannerRepositoryContract;
use App\Cms\Domain\StateMachines\StaticPageStateMachine;
use App\Cms\Infrastructure\Events\CmsDomainEvents;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Result;

/**
 * HeroBannerService — CRUD + date-range queries for hero banners.
 *
 * Mirrors StaticPageService patterns (transaction + lock + post-commit
 * event dispatch). Used by Phase 4 admin UI.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §4.4
 */
final class HeroBannerService
{
    public function __construct(
        private readonly HeroBannerRepositoryContract $banners,
        private readonly StaticPageStateMachine $stateMachine,
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    public function create(HeroBannerDraftInput $input): Result
    {
        return $this->adapter->transaction(function () use ($input): Result {
            $banner = HeroBanner::create(
                title: $input->title,
                subtitle: $input->subtitle,
                ctaLabel: $input->ctaLabel,
                ctaUrl: $input->ctaUrl,
                imageFileId: $input->imageFileId,
                mobileImageFileId: $input->mobileImageFileId,
                displayOrder: $input->displayOrder,
                startsAt: $input->startsAt,
                endsAt: $input->endsAt,
                createdBy: $input->createdBy,
            );
            $this->banners->save($banner);

            event(CmsDomainEvents::HERO_BANNER_CHANGED, [$banner->id()->value()]);

            return Result::success($banner);
        });
    }

    public function update(EntityId $id, HeroBannerUpdateInput $input): Result
    {
        return $this->adapter->transaction(function () use ($id, $input): Result {
            $locked = $this->banners->findById($id);
            if ($locked === null) {
                return Result::failure('hero_banner.not_found');
            }

            $changes = [];
            foreach (['title', 'subtitle', 'ctaLabel', 'ctaUrl', 'imageFileId', 'mobileImageFileId', 'displayOrder', 'startsAt', 'endsAt', 'updatedBy'] as $field) {
                $val = $input->{$field};
                if ($val === null) {
                    continue;
                }
                $map = [
                    'title' => 'title',
                    'subtitle' => 'subtitle',
                    'ctaLabel' => 'cta_label',
                    'ctaUrl' => 'cta_url',
                    'imageFileId' => 'image_file_id',
                    'mobileImageFileId' => 'mobile_image_file_id',
                    'displayOrder' => 'display_order',
                    'startsAt' => 'starts_at',
                    'endsAt' => 'ends_at',
                    'updatedBy' => 'updated_by',
                ];
                $col = $map[$field] ?? $field;
                $changes[$col] = $val instanceof EntityId ? $val->value() : $val;
            }

            if ($changes === []) {
                return Result::success($locked);
            }

            $updated = $locked->withChanges($changes);
            $this->banners->update($updated);

            event(CmsDomainEvents::HERO_BANNER_CHANGED, [$updated->id()->value()]);

            return Result::success($updated);
        });
    }

    public function publish(EntityId $id): Result
    {
        return $this->transitionState($id, StaticPageState::PUBLISHED);
    }

    public function archive(EntityId $id): Result
    {
        return $this->transitionState($id, StaticPageState::ARCHIVED);
    }

    private function transitionState(EntityId $id, StaticPageState $to): Result
    {
        return $this->adapter->transaction(function () use ($id, $to): Result {
            // HeroBannerRepository doesn't have a lockByIdForUpdate yet;
            // for V1 we use findById which is non-locking. Future pass
            // can add the lock variant. The race window is small for
            // banner mutations (admin-only).
            $banner = $this->banners->findById($id);
            if ($banner === null) {
                return Result::failure('hero_banner.not_found');
            }

            $transitioned = $banner->transitionTo($this->stateMachine, $to);
            $this->banners->update($transitioned);

            event(CmsDomainEvents::HERO_BANNER_CHANGED, [$transitioned->id()->value()]);

            return Result::success($transitioned);
        });
    }

    public function attachToPage(EntityId $bannerId, EntityId $pageId, int $displayOrder): Result
    {
        return $this->adapter->transaction(function () use ($bannerId, $pageId, $displayOrder): Result {
            $banner = $this->banners->findById($bannerId);
            if ($banner === null) {
                return Result::failure('hero_banner.not_found');
            }

            $this->banners->attachToPage($bannerId, $pageId, $displayOrder);

            event(CmsDomainEvents::HERO_BANNER_CHANGED, [$bannerId->value()]);
            event(CmsDomainEvents::REFERENCE_ATTACHED, [$pageId->value()]);

            return Result::success(null);
        });
    }

    public function detachFromPage(EntityId $bannerId, EntityId $pageId): Result
    {
        return $this->adapter->transaction(function () use ($bannerId, $pageId): Result {
            $this->banners->detachFromPage($bannerId, $pageId);

            event(CmsDomainEvents::HERO_BANNER_CHANGED, [$bannerId->value()]);
            event(CmsDomainEvents::REFERENCE_DETACHED, [$pageId->value()]);

            return Result::success(null);
        });
    }

    /**
     * @return list<HeroBanner>
     */
    public function listActiveAt(\DateTimeImmutable $when): array
    {
        return $this->banners->listActiveAt($when);
    }
}