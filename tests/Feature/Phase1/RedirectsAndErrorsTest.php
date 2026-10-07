<?php

declare(strict_types=1);

namespace Tests\Feature\Phase1;

use App\Http\Middleware\HandleInertiaRequests;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

final class RedirectsAndErrorsTest extends InfrastructureTestCase
{
    public function test_unpublished_trustee_and_mission_301_to_about(): void
    {
        $this->get('/trustee')->assertStatus(301)->assertRedirect('/about');
        $this->get('/mission')->assertStatus(301)->assertRedirect('/about');
    }

    public function test_other_missing_cms_pages_still_404_and_are_noindex(): void
    {
        $response = $this->get('/policies');

        $response->assertStatus(404);
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_inertia_error_page_is_noindex(): void
    {
        $version = (string) app(HandleInertiaRequests::class)
            ->version(request());
        $response = $this->get('/policies', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version]);

        $response->assertStatus(404);
        $this->assertTrue(
            collect($response->json('props.seo.tags'))->contains(
                fn ($t) => ($t['attrs']['name'] ?? null) === 'robots'
            ),
        );

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
