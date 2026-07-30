<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Domain\DTOs\StaticPageDraftInput;
use App\Cms\Domain\DTOs\StaticPageUpdateInput;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\Exceptions\DuplicatePageSlugException;
use App\Cms\Domain\Exceptions\HomepageAlreadyAssignedException;
use App\Cms\Domain\Exceptions\StaticPageNotFoundException;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Domain\StateMachines\StaticPageStateMachine;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Infrastructure\Events\CmsDomainEvents;
use App\Cms\Infrastructure\Rendering\StaticPageBodyRenderer;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Result;

/**
 * StaticPageService — authoring-side API for the CMS kernel.
 *
 * Used by Phase 4 admin UI for all static page mutations. Not called
 * by the Phase 3 frontend (which uses StaticPageQueryService +
 * StaticPageRendererService for reads).
 *
 * Every mutating method wraps in $adapter->transaction() with a
 * SELECT FOR UPDATE row lock on the affected page(s). Cache invalidation
 * events are dispatched AFTER commit (commit-then-notify pattern from
 * Payments kernel) to prevent cache from being invalidated then re-
 * populated with stale data on rollback.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §4.1
 */
final class StaticPageService
{
    public function __construct(
        private readonly StaticPageRepositoryContract $pages,
        private readonly StaticPageStateMachine $stateMachine,
        private readonly PersistenceAdapterContract $adapter,
        private readonly StaticPageBodyRenderer $bodyRenderer,
        private readonly HomepageContentFactory $homepageContentFactory,
    ) {
    }

    public function createDraft(StaticPageDraftInput $input): Result
    {
        // 1. Validate slug format
        try {
            $slug = new PageSlug($input->slug);
        } catch (\InvalidArgumentException) {
            return Result::failure('slug.invalid_format');
        }

        // 2. Pre-check uniqueness
        if ($this->pages->existsBySlug($slug)) {
            return Result::failure('slug.duplicate');
        }

        // 2b. App-layer single-homepage pre-check (drive-by B).
        // The Postgres EXCLUDE constraint `static_pages_single_homepage`
        // (defined in V1-schema.sql:277-279 and enforced via btree_gist)
        // catches duplicate homepages on INSERT. SQLite does NOT support
        // EXCLUDE constraints, so we enforce the invariant here on the
        // application side. The check runs in the same transaction as
        // the save() below; on pgsql the catch() arm at line 86 is the
        // safety net, on sqlite this pre-check is the primary.
        if ($input->isHomepage && $this->pages->findHomepage() !== null) {
            return Result::failure('homepage.conflict');
        }

        // 2c. Re-validate any supplied homepageContent against the current
        // database state so a fabricated ID set cannot bypass the boundary.
        if ($input->homepageContent !== null) {
            try {
                $this->homepageContentFactory->assertStillValid($input->homepageContent);
            } catch (\InvalidArgumentException $e) {
                return Result::failure('homepage_content.invalid:'.$e->getMessage());
            }
        }

        // 3. Persist inside transaction
        $result = $this->adapter->transaction(function () use ($input, $slug): Result {
            try {
                $body = PageBody::fromArray([
                    'version' => PageBody::CURRENT_VERSION,
                    'blocks' => $input->bodyBlocks,
                ]);
                $page = StaticPage::draft(
                    slug: $slug,
                    title: $input->title,
                    metaDescription: $input->metaDescription,
                    body: $body,
                    seoMetadata: $input->seoMetadata,
                    isHomepage: $input->isHomepage,
                    displayOrder: $input->displayOrder,
                    createdBy: $input->createdBy,
                    homepageContent: $input->homepageContent,
                );
                $this->pages->save($page);

                return Result::success($page);
            } catch (DuplicatePageSlugException) {
                return Result::failure('slug.duplicate');
            } catch (HomepageAlreadyAssignedException) {
                return Result::failure('homepage.conflict');
            }
        });

        return $result;
    }

    public function updateContent(EntityId $id, StaticPageUpdateInput $input): Result
    {
        // Pre-validate any supplied homepageContent before opening the
        // transaction so we can fail fast with a clean error.
        if ($input->homepageContent !== null) {
            try {
                $this->homepageContentFactory->assertStillValid($input->homepageContent);
            } catch (\InvalidArgumentException $e) {
                return Result::failure('homepage_content.invalid:'.$e->getMessage());
            }
        }

        return $this->adapter->transaction(function () use ($id, $input): Result {
            $locked = $this->pages->lockByIdForUpdate($id);
            if ($locked === null) {
                return Result::failure('static_page.not_found');
            }

            $changes = [];
            if ($input->title !== null) {
                $changes['title'] = $input->title;
            }
            if ($input->metaDescription !== null) {
                $changes['meta_description'] = $input->metaDescription;
            }
            if ($input->bodyBlocks !== null) {
                $body = PageBody::fromArray([
                    'version' => PageBody::CURRENT_VERSION,
                    'blocks' => $input->bodyBlocks,
                ]);
                $changes['body_json'] = json_encode($body->toArray(), JSON_THROW_ON_ERROR);
            }
            if ($input->seoMetadata !== null) {
                $changes['seo_metadata'] = json_encode($input->seoMetadata->toArray(), JSON_THROW_ON_ERROR);
            }
            if ($input->displayOrder !== null) {
                $changes['display_order'] = $input->displayOrder;
            }
            if ($input->updatedBy !== null) {
                $changes['updated_by'] = $input->updatedBy;
            }

            $updated = $changes === [] ? $locked : $locked->withChanges($changes);

            if ($input->homepageContent !== null) {
                $updated = $updated->withHomepageContent($input->homepageContent);
            } elseif ($input->clearHomepageContent) {
                $updated = $updated->withHomepageContent(null);
            }

            if ($updated === $locked) {
                return Result::success($locked);
            }

            $this->pages->update($updated);

            // Event dispatched post-commit (transaction wrapper completes below)
            event(CmsDomainEvents::STATIC_PAGE_BODY_CHANGED, [$updated->slug()->value()]);

            return Result::success($updated);
        });
    }

    public function publish(EntityId $id): Result
    {
        return $this->adapter->transaction(function () use ($id): Result {
            $locked = $this->pages->lockByIdForUpdate($id);
            if ($locked === null) {
                return Result::failure('static_page.not_found');
            }

            $transitioned = $locked->transitionTo(
                $this->stateMachine,
                StaticPageState::PUBLISHED,
            );

            // Re-render body_json → body_html (the canonical render output cache)
            $newHtml = $this->bodyRenderer->render($transitioned->body());
            $withHtml = $transitioned->withChanges(['body_html' => $newHtml]);

            $this->pages->update($withHtml);

            event(CmsDomainEvents::STATIC_PAGE_PUBLISHED, [$withHtml->slug()->value()]);

            return Result::success($withHtml);
        });
    }

    public function markAsEdited(EntityId $id): Result
    {
        return $this->adapter->transaction(function () use ($id): Result {
            $locked = $this->pages->lockByIdForUpdate($id);
            if ($locked === null) {
                return Result::failure('static_page.not_found');
            }

            $transitioned = $locked->transitionTo(
                $this->stateMachine,
                StaticPageState::UPDATED,
            );
            $this->pages->update($transitioned);

            event(CmsDomainEvents::STATIC_PAGE_UPDATED, [$transitioned->slug()->value()]);

            return Result::success($transitioned);
        });
    }

    public function archive(EntityId $id): Result
    {
        return $this->adapter->transaction(function () use ($id): Result {
            $locked = $this->pages->lockByIdForUpdate($id);
            if ($locked === null) {
                return Result::failure('static_page.not_found');
            }

            $transitioned = $locked->transitionTo(
                $this->stateMachine,
                StaticPageState::ARCHIVED,
            );
            $this->pages->update($transitioned);

            event(CmsDomainEvents::STATIC_PAGE_ARCHIVED, [$transitioned->slug()->value()]);

            return Result::success($transitioned);
        });
    }

    public function restore(EntityId $id): Result
        {
            // Restore is a privileged admin operation: ARCHIVED → DRAFT.
            // The SM allows this transition (ARCHIVED accepts only
            // PAGE_RESTORED); all other events from ARCHIVED are refused.
            return $this->adapter->transaction(function () use ($id): Result {
                $locked = $this->pages->lockByIdForUpdate($id);
                if ($locked === null) {
                    return Result::failure('static_page.not_found');
                }
                if ($locked->state() !== StaticPageState::ARCHIVED) {
                    return Result::failure('state.transition.invalid');
                }

                $restored = $locked->transitionTo(
                    $this->stateMachine,
                    StaticPageState::DRAFT,
                );
                $this->pages->update($restored);

                event(CmsDomainEvents::STATIC_PAGE_BODY_CHANGED, [$restored->slug()->value()]);

                return Result::success($restored);
            });
        }

    public function assignHomepage(EntityId $pageId): Result
    {
        return $this->adapter->transaction(function () use ($pageId): Result {
            $target = $this->pages->lockByIdForUpdate($pageId);
            if ($target === null) {
                return Result::failure('static_page.not_found');
            }

            $current = $this->pages->findHomepage();

            // Idempotent: if target is already the homepage, success
            if ($current !== null && $current->id()->equals($target->id())) {
                return Result::success($target);
            }

            $oldSlug = $current?->slug()->value();

            // Lock + clear the existing homepage
            if ($current !== null) {
                $lockedCurrent = $this->pages->lockByIdForUpdate($current->id());
                if ($lockedCurrent->isHomepage() && ! $lockedCurrent->id()->equals($target->id())) {
                    $cleared = $lockedCurrent->clearHomepage();
                    $this->pages->update($cleared);
                }
            }

            // Mark target as homepage
            try {
                $marked = $target->markAsHomepage();
                $this->pages->update($marked);
            } catch (HomepageAlreadyAssignedException $e) {
                return Result::failure('homepage.conflict');
            }

            event(CmsDomainEvents::HOMEPAGE_CHANGED, [$oldSlug, $marked->slug()->value()]);

            return Result::success($marked);
        });
    }

    public function softDelete(EntityId $id): Result
    {
        return $this->adapter->transaction(function () use ($id): Result {
            $locked = $this->pages->lockByIdForUpdate($id);
            if ($locked === null) {
                return Result::failure('static_page.not_found');
            }

            $this->pages->softDelete($id);

            event(CmsDomainEvents::STATIC_PAGE_DELETED, [$locked->slug()->value()]);

            return Result::success(null);
        });
    }
}
