<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Cms\Content\PrivacyPageDefinition;
use App\Cms\Content\TermsPageDefinition;
use App\Cms\Contracts\ResolvedPageCacheContract;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Infrastructure\Rendering\StaticPageBodyRenderer;
use App\Persistence\Contracts\PersistenceAdapterContract;
use Illuminate\Database\Seeder;

/**
 * Idempotently publishes only the Privacy and Terms CMS pages.
 *
 * This seeder is intentionally separate from ProductionSeeder: publishing
 * legal policy content must not reseed or modify campaigns or other
 * operational records. Run explicitly with
 * `db:seed --class=Database\\Seeders\\LegalPolicyPagesSeeder`.
 */
final class LegalPolicyPagesSeeder extends Seeder
{
    public function run(): void
    {
        /** @var PersistenceAdapterContract $adapter */
        $adapter = $this->container->make(PersistenceAdapterContract::class);
        $renderer = $this->container->make(StaticPageBodyRenderer::class);
        $cache = $this->container->make(ResolvedPageCacheContract::class);

        $this->seedPage(
            $adapter,
            $renderer,
            $cache,
            TermsPageDefinition::ID,
            TermsPageDefinition::SLUG,
            TermsPageDefinition::TITLE,
            TermsPageDefinition::META_DESCRIPTION,
            TermsPageDefinition::DISPLAY_ORDER,
            TermsPageDefinition::bodyBlocks(),
            ['terms', 'donations', 'campaigns'],
        );

        $this->seedPage(
            $adapter,
            $renderer,
            $cache,
            PrivacyPageDefinition::ID,
            PrivacyPageDefinition::SLUG,
            PrivacyPageDefinition::TITLE,
            PrivacyPageDefinition::META_DESCRIPTION,
            PrivacyPageDefinition::DISPLAY_ORDER,
            PrivacyPageDefinition::bodyBlocks(),
            ['privacy', 'personal information', 'donations'],
        );

        $this->command->info('Privacy and Terms pages published.');
    }

    /**
     * @param  list<array{type: 'heading', level: 2, text: string}|array{type: 'paragraph', text: string}>  $blocks
     * @param  list<string>  $keywords
     */
    private function seedPage(
        PersistenceAdapterContract $adapter,
        StaticPageBodyRenderer $renderer,
        ResolvedPageCacheContract $cache,
        string $id,
        string $slug,
        string $title,
        string $metaDescription,
        int $displayOrder,
        array $blocks,
        array $keywords,
    ): void {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $body = PageBody::fromArray([
            'version' => PageBody::CURRENT_VERSION,
            'blocks' => $blocks,
        ]);
        $bodyJson = json_encode($body->toArray(), JSON_THROW_ON_ERROR);
        $bodyHtml = $renderer->render($body);
        $seoJson = json_encode([
            'meta_title' => $title,
            'meta_description' => $metaDescription,
            'canonical_url' => null,
            'og_image_file_id' => null,
            'keywords' => $keywords,
        ], JSON_THROW_ON_ERROR);

        $result = $adapter->execute(
            "INSERT INTO static_pages (
                id, slug, title, meta_description,
                body_json, body_html, seo_metadata,
                homepage_content, about_page_content, legal_page_content,
                state, is_homepage, display_order,
                published_at, last_published_at,
                created_at, updated_at, created_by, updated_by
             ) VALUES (
                :id, :slug, :title, :meta_description,
                :body_json, :body_html, :seo_metadata,
                NULL, NULL, NULL,
                'published', FALSE, :display_order,
                :now, :now,
                :now, :now, 'system', 'system'
             )
             ON CONFLICT (slug) WHERE deleted_at IS NULL DO UPDATE SET
                title              = EXCLUDED.title,
                meta_description   = EXCLUDED.meta_description,
                body_json          = EXCLUDED.body_json,
                body_html          = EXCLUDED.body_html,
                seo_metadata       = EXCLUDED.seo_metadata,
                state              = EXCLUDED.state,
                is_homepage        = EXCLUDED.is_homepage,
                display_order      = EXCLUDED.display_order,
                published_at       = COALESCE(static_pages.published_at, EXCLUDED.published_at),
                last_published_at  = EXCLUDED.last_published_at,
                updated_at         = EXCLUDED.updated_at,
                updated_by         = EXCLUDED.updated_by",
            [
                'id' => $id,
                'slug' => $slug,
                'title' => $title,
                'meta_description' => $metaDescription,
                'body_json' => $bodyJson,
                'body_html' => $bodyHtml,
                'seo_metadata' => $seoJson,
                'display_order' => $displayOrder,
                'now' => $now,
            ],
        );

        if ($result->isFailure()) {
            throw new \RuntimeException("Unable to seed /{$slug}: ".($result->error() ?? 'unknown error'));
        }

        $cache->invalidate(new PageSlug($slug));
    }
}
