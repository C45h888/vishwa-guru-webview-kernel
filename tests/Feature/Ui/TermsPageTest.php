<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Cms\Content\TermsPageDefinition;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Domain\ValueObjects\PageSlug;
use Database\Seeders\LegalPolicyPagesSeeder;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

final class TermsPageTest extends InfrastructureTestCase
{
    public function test_terms_seeder_publishes_the_canonical_content_through_the_public_route(): void
    {
        $this->artisan('db:seed', [
            '--class' => LegalPolicyPagesSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);
        $this->artisan('db:seed', [
            '--class' => LegalPolicyPagesSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);

        $response = $this->get('/terms');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->component('cms/Page')
            ->where('page.slug', TermsPageDefinition::SLUG)
            ->where('page.title', TermsPageDefinition::TITLE)
            ->where('html', fn (string $renderedHtml): bool => str_contains($renderedHtml, 'If contributions exceed the target')
                && str_contains($renderedHtml, 'children in the Trust’s care')
                && str_contains($renderedHtml, 'handle any applicable refund manually'))
            ->etc()
        );

        /** @var StaticPageRepositoryContract $pages */
        $pages = $this->app->make(StaticPageRepositoryContract::class);
        $stored = $pages->findBySlug(new PageSlug(TermsPageDefinition::SLUG));
        self::assertNotNull($stored);
        self::assertSame('published', $stored->state()->value);

        $privacyResponse = $this->get('/privacy');
        $privacyResponse->assertOk();
        $privacyResponse->assertInertia(fn (AssertableInertia $inertia) => $inertia
            ->component('cms/Page')
            ->where('page.slug', 'privacy')
            ->where('page.title', 'Privacy Policy')
            ->where('html', fn (string $renderedHtml): bool => str_contains($renderedHtml, 'future campaign or promotional email')
                && str_contains($renderedHtml, 'accounting and compliance'))
            ->etc()
        );
    }
}
