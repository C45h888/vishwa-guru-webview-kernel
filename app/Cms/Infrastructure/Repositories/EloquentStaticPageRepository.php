<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Repositories;

use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\Exceptions\DuplicatePageSlugException;
use App\Cms\Domain\Exceptions\HomepageAlreadyAssignedException;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use RuntimeException;

/**
 * Eloquent implementation of StaticPageRepositoryContract.
 *
 * Uses PersistenceAdapterContract for all DB access (no Eloquent models
 * leak). Translates DB constraint violations (unique slug, EXCLUDE
 * homepage) into domain exceptions at this boundary so callers see
 * clean business errors.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §5.1
 *
 * @implements StaticPageRepositoryContract
 */
final class EloquentStaticPageRepository implements StaticPageRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    public function findById(EntityId $id): ?StaticPage
    {
        $result = $this->adapter->query(
            'SELECT * FROM static_pages WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return StaticPage::fromRow($result->value()[0]);
    }

    public function findBySlug(PageSlug $slug): ?StaticPage
    {
        $result = $this->adapter->query(
            'SELECT * FROM static_pages WHERE slug = :slug AND deleted_at IS NULL LIMIT 1',
            ['slug' => $slug->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return StaticPage::fromRow($result->value()[0]);
    }

    public function findHomepage(): ?StaticPage
    {
        $result = $this->adapter->query(
            'SELECT * FROM static_pages WHERE is_homepage = TRUE AND deleted_at IS NULL LIMIT 1',
            [],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return StaticPage::fromRow($result->value()[0]);
    }

    public function listPublished(?int $limit = null, ?int $offset = null): array
    {
        $sql = 'SELECT * FROM static_pages WHERE state = :state AND deleted_at IS NULL ORDER BY display_order ASC';
        $params = ['state' => StaticPageState::PUBLISHED->value];
        if ($limit !== null) {
            $sql .= ' LIMIT :lim';
            $params['lim'] = $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET :off';
            $params['off'] = $offset;
        }

        return $this->fetchList($sql, $params);
    }

    public function listByState(StaticPageState $state, ?int $limit = null): array
    {
        $sql = 'SELECT * FROM static_pages WHERE state = :state AND deleted_at IS NULL ORDER BY display_order ASC';
        $params = ['state' => $state->value];
        if ($limit !== null) {
            $sql .= ' LIMIT :lim';
            $params['lim'] = $limit;
        }

        return $this->fetchList($sql, $params);
    }

    public function listAll(?int $limit = null, ?int $offset = null): array
    {
        $sql = 'SELECT * FROM static_pages ORDER BY display_order ASC';
        $params = [];
        if ($limit !== null) {
            $sql .= ' LIMIT :lim';
            $params['lim'] = $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET :off';
            $params['off'] = $offset;
        }

        return $this->fetchList($sql, $params);
    }

    public function save(StaticPage $page): void
    {
        $row = $page->toArray();

        $sql = 'INSERT INTO static_pages (
            id, slug, title, meta_description, body_json, body_html,
            seo_metadata, state, is_homepage, display_order,
            published_at, last_published_at, created_at, updated_at,
            deleted_at, created_by, updated_by
        ) VALUES (
            :id, :slug, :title, :meta_description, :body_json, :body_html,
            :seo_metadata, :state, :is_homepage, :display_order,
            :published_at, :last_published_at, :created_at, :updated_at,
            :deleted_at, :created_by, :updated_by
        )';

        $params = [
            'id' => $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'meta_description' => $row['meta_description'],
            'body_json' => $row['body_json'],
            'body_html' => $row['body_html'],
            'seo_metadata' => $row['seo_metadata'],
            'state' => $row['state'],
            'is_homepage' => $row['is_homepage'],
            'display_order' => $row['display_order'],
            'published_at' => $row['published_at'],
            'last_published_at' => $row['last_published_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'],
            'created_by' => $row['created_by'],
            'updated_by' => $row['updated_by'],
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            $this->translateConstraintViolation($exec->error() ?? '', $row['slug']);
        }
    }

    public function update(StaticPage $page): void
    {
        $row = $page->toArray();

        $sql = 'UPDATE static_pages SET
            slug = :slug,
            title = :title,
            meta_description = :meta_description,
            body_json = :body_json,
            body_html = :body_html,
            seo_metadata = :seo_metadata,
            state = :state,
            is_homepage = :is_homepage,
            display_order = :display_order,
            published_at = :published_at,
            last_published_at = :last_published_at,
            updated_at = :updated_at,
            deleted_at = :deleted_at,
            updated_by = :updated_by
        WHERE id = :id';

        $params = [
            'id' => $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'meta_description' => $row['meta_description'],
            'body_json' => $row['body_json'],
            'body_html' => $row['body_html'],
            'seo_metadata' => $row['seo_metadata'],
            'state' => $row['state'],
            'is_homepage' => $row['is_homepage'],
            'display_order' => $row['display_order'],
            'published_at' => $row['published_at'],
            'last_published_at' => $row['last_published_at'],
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'],
            'updated_by' => $row['updated_by'],
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            $this->translateConstraintViolation($exec->error() ?? '', $row['slug']);
        }
    }

    public function softDelete(EntityId $id): void
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $exec = $this->adapter->execute(
            'UPDATE static_pages SET deleted_at = :now, updated_at = :now WHERE id = :id',
            ['id' => $id->value(), 'now' => $now],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentStaticPageRepository::softDelete failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    public function existsBySlug(PageSlug $slug, ?EntityId $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM static_pages WHERE slug = :slug AND deleted_at IS NULL';
        $params = ['slug' => $slug->value()];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude';
            $params['exclude'] = $excludeId->value();
        }
        $sql .= ' LIMIT 1';

        $result = $this->adapter->query($sql, $params);

        return ! $result->isFailure() && ! empty($result->value());
    }

    public function lockBySlugForUpdate(PageSlug $slug): ?StaticPage
    {
        $result = $this->adapter->query(
            'SELECT * FROM static_pages WHERE slug = :slug AND deleted_at IS NULL FOR UPDATE LIMIT 1',
            ['slug' => $slug->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return StaticPage::fromRow($result->value()[0]);
    }

    public function lockByIdForUpdate(EntityId $id): ?StaticPage
    {
        $result = $this->adapter->query(
            'SELECT * FROM static_pages WHERE id = :id AND deleted_at IS NULL FOR UPDATE LIMIT 1',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return StaticPage::fromRow($result->value()[0]);
    }

    public function countByState(StaticPageState $state): int
    {
        $result = $this->adapter->query(
            'SELECT COUNT(*) AS cnt FROM static_pages WHERE state = :state AND deleted_at IS NULL',
            ['state' => $state->value],
        );
        if ($result->isFailure() || empty($result->value())) {
            return 0;
        }

        return (int) ($result->value()[0]['cnt'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<StaticPage>
     */
    private function fetchList(string $sql, array $params): array
    {
        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row) => StaticPage::fromRow($row),
            $result->value(),
        );
    }

    /**
     * Translate DB constraint violation messages into domain exceptions.
     * Detects two patterns:
     *   1. Unique slug violation → DuplicatePageSlugException
     *   2. Homepage EXCLUDE constraint → HomepageAlreadyAssignedException
     *
     * String-match approach matches the Payments kernel pattern; future
     * migration to typed exceptions is a Shared-kernel concern.
     */
    private function translateConstraintViolation(string $errorMessage, string $slug): never
    {
        if (str_contains($errorMessage, 'slug_live_idx')
            || str_contains($errorMessage, 'unique constraint')
            || str_contains($errorMessage, 'duplicate key')) {
            throw DuplicatePageSlugException::forSlug($slug);
        }

        if (str_contains($errorMessage, 'static_pages_single_homepage')
            || str_contains($errorMessage, 'exclude constraint')) {
            throw HomepageAlreadyAssignedException::between('unknown', 'unknown');
        }

        throw new RuntimeException(
            'EloquentStaticPageRepository constraint violation: '.$errorMessage
        );
    }
}