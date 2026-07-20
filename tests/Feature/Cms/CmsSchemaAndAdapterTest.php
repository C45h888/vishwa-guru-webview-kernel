<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Cms\Domain\Enums\PageReferenceType;
use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\Repositories\StaticPageReferenceRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Infrastructure\Persistence\Mappers\StaticPageMapper;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use App\Cms\Domain\ValueObjects\Blocks\ParagraphBlock;
use App\Cms\Domain\Entities\StaticPage;
use App\Persistence\ValueObjects\EntityId;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * SQLite-migration + adapter round-trip tests for the CMS kernel.
 *
 * These tests are the load-bearing verification for the new
 * database/migrations/2026_07_16_000005_create_cms_tables_sqlite.php
 * migration. They run on the in-memory SQLite DB via the inherited
 * RefreshDatabase + InfrastructureTestCase base.
 */
final class CmsSchemaAndAdapterTest extends InfrastructureTestCase
{
    public function testAllFiveCmsTablesExistAfterMigration(): void
    {
        $expected = [
            'static_pages',
            'hero_banners',
            'hero_banner_pages',
            'static_page_references',
            'contact_information',
        ];

        $result = $this->adapter->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name IN ("
            .implode(',', array_fill(0, count($expected), '?'))
            .')',
            $expected,
        );

        $this->assertFalse($result->isFailure(), 'sqlite_master query failed');

        $found = array_column($result->value(), 'name');

        foreach ($expected as $table) {
            $this->assertContains(
                $table,
                $found,
                "CMS table '{$table}' is missing after migration"
            );
        }
    }

    public function testStaticPageInsertAndReadRoundTrip(): void
    {
        /** @var StaticPageRepositoryContract $repo */
        $repo = $this->app->make(StaticPageRepositoryContract::class);

        $body = new PageBody(version: 1, blocks: [
            new ParagraphBlock(text: 'Hello from the round-trip test.'),
        ]);
        $seo = new SeoMetadata(metaTitle: 'Round Trip', keywords: ['test']);

        $page = StaticPage::draft(
            slug: new PageSlug('round-trip-sqlite'),
            title: 'Round Trip SQLite',
            metaDescription: 'm',
            body: $body,
            seoMetadata: $seo,
            isHomepage: false,
            displayOrder: 42,
            createdBy: 'system',
            id: EntityId::fromString('static_page_01HROUNDTRIP'),
        );

        $repo->save($page);

        $found = $repo->findById($page->id());
        $this->assertNotNull($found, 'findById returned null after save');
        $this->assertSame($page->id()->value(), $found->id()->value());
        $this->assertSame($page->slug()->value(), $found->slug()->value());
        $this->assertSame($page->title(), $found->title());
        $this->assertSame($page->state(), $found->state());

        // Mapper round-trip on the way back: prove Mapper::fromRow decodes
        // the row the repo returned (the same decode path every read uses).
        $row = StaticPageMapper::toRow($found);
        $rehydrated = StaticPageMapper::fromRow($row);
        $this->assertSame($page->id()->value(), $rehydrated->id()->value());
        $this->assertSame($page->displayOrder(), $rehydrated->displayOrder());
    }

    /**
     * Drive-by A: codifies the SQLite-portable NULL-safe context
     * comparison in EloquentStaticPageReferenceRepository::existsForPage().
     *
     * Setup: two references with the same (page, type, target) but
     * different context values — one NULL, one 'rail'. The repo must
     * return true for the matching context and false for the others.
     * Without the rewrite, `context = NULL` would never match NULL
     * contexts and the second reference would silently slip through.
     */
    public function testReferenceContextComparisonIsNullSafe(): void
    {
        /** @var StaticPageReferenceRepositoryContract $references */
        $references = $this->app->make(StaticPageReferenceRepositoryContract::class);

        // Insert a page (for the FK).
        /** @var StaticPageRepositoryContract $pages */
        $pages = $this->app->make(StaticPageRepositoryContract::class);
        $pages->save(StaticPage::draft(
            slug: new PageSlug('ctx-test'),
            title: 'Context Test',
            metaDescription: null,
            body: new PageBody(version: 1, blocks: [new ParagraphBlock(text: 'x')]),
            seoMetadata: new SeoMetadata(),
            isHomepage: false,
            displayOrder: 0,
            createdBy: 'system',
        ));
        $pageId = $pages->findBySlug(new PageSlug('ctx-test'))->id();

        $campaignId = EntityId::fromString('campaign_01HCTXCTX');

        $references->attach(\App\Cms\Domain\Entities\StaticPageReference::create(
            staticPageId: $pageId,
            referenceType: PageReferenceType::CAMPAIGN,
            referenceId: $campaignId,
            displayOrder: 0,
            context: null,
        ));
        $references->attach(\App\Cms\Domain\Entities\StaticPageReference::create(
            staticPageId: $pageId,
            referenceType: PageReferenceType::CAMPAIGN,
            referenceId: $campaignId,
            displayOrder: 1,
            context: 'rail',
        ));

        $this->assertTrue(
            $references->existsForPage($pageId, PageReferenceType::CAMPAIGN, $campaignId, null),
            'existsForPage with context=null must match the NULL-context row'
        );
        $this->assertTrue(
            $references->existsForPage($pageId, PageReferenceType::CAMPAIGN, $campaignId, 'rail'),
            "existsForPage with context='rail' must match the rail-context row"
        );
        $this->assertFalse(
            $references->existsForPage($pageId, PageReferenceType::CAMPAIGN, $campaignId, 'other'),
            'existsForPage with context=other must NOT match either row'
        );
    }
}
