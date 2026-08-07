<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UploadControllerTest — admin media upload endpoint.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel" Pass 2 / Pass 3):
 *   - The schema models public media in TWO layers:
 *       1. file_assets      — physical file (owner_type is a valid
 *                             file_owner_type enum value).
 *       2. cms_media_assets — published presentation overlay that
 *                             PublicMediaQuery / /media/{id} read from,
 *                             and that campaigns.cover_image_file_id /
 *                             events.banner_file_id FK reference.
 *     The upload creates BOTH rows and returns the cms_media_assets.id,
 *     so the returned id is immediately displayable and FK-valid.
 *   - A `purpose` discriminator selects the owning service:
 *       purpose='campaign_cover' → CampaignCoverUploadService
 *       purpose='event_cover'    → EventBannerUploadService
 *   - Auth is enforced by the route group's [web, auth, admin] pipeline.
 */
final class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /**
     * A tiny valid image (1x1 transparent PNG) so the upload service's
     * MIME + size validation passes.
     */
    private function validImage(): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            true,
        );

        $path = tempnam(sys_get_temp_dir(), 'tst') . '.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, 'banner.png', 'image/png', null, true);
    }

    #[Test]
    public function test_campaign_cover_returns_displayable_cms_media_id(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())
            ->post('/admin/media/upload', [
                'file' => $this->validImage(),
                'purpose' => 'campaign_cover',
            ])
            ->assertCreated()
            ->assertJsonStructure(['id']);

        $cmsId = json_decode($response->getContent(), true)['id'];

        // The returned id is a cms_media_assets.id, not a file_assets.id.
        $cms = DB::table('cms_media_assets')->where('id', $cmsId)->first();
        $this->assertNotNull($cms, 'returned id must be a cms_media_assets.id');
        $this->assertSame('campaign_cover', $cms->media_type);
        $this->assertSame('published', $cms->state);

        // The file_asset row underneath is a valid owner_type + on the right path.
        $file = DB::table('file_assets')->where('id', $cms->file_asset_id)->first();
        $this->assertNotNull($file);
        $this->assertSame('campaign_cover', $file->owner_type);
        $this->assertStringContainsString('campaign-covers/', $file->storage_path);

        // The overlay is resolvable through the public presentation query.
        $projection = $this->app->make(\App\Cms\Contracts\PublicMediaQueryContract::class)->find($cmsId);
        $this->assertNotNull($projection, '/media/{id} must resolve the uploaded image');
        $this->assertTrue($projection->isDisplayable());
    }

    #[Test]
    public function test_event_cover_returns_displayable_cms_media_id(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())
            ->post('/admin/media/upload', [
                'file' => $this->validImage(),
                'purpose' => 'event_cover',
            ])
            ->assertCreated()
            ->assertJsonStructure(['id']);

        $cmsId = json_decode($response->getContent(), true)['id'];

        $cms = DB::table('cms_media_assets')->where('id', $cmsId)->first();
        $this->assertNotNull($cms, 'returned id must be a cms_media_assets.id');
        $this->assertSame('event_banner', $cms->media_type);
        $this->assertSame('published', $cms->state);

        $file = DB::table('file_assets')->where('id', $cms->file_asset_id)->first();
        $this->assertNotNull($file);
        $this->assertSame('event_banner', $file->owner_type);
        $this->assertStringContainsString('event-banners/', $file->storage_path);

        $projection = $this->app->make(\App\Cms\Contracts\PublicMediaQueryContract::class)->find($cmsId);
        $this->assertNotNull($projection, '/media/{id} must resolve the uploaded image');
        $this->assertTrue($projection->isDisplayable());
    }

    #[Test]
    public function test_missing_purpose_fails_validation(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/media/upload', [
                'file' => $this->validImage(),
            ])
            ->assertStatus(302); // validation failure redirects (non-JSON validation)
    }

    #[Test]
    public function test_invalid_purpose_fails_validation(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/media/upload', [
                'file' => $this->validImage(),
                'purpose' => 'avatar',
            ])
            ->assertStatus(302);
    }

    #[Test]
    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->post('/admin/media/upload', [
            'file' => $this->validImage(),
            'purpose' => 'event_cover',
        ])->assertRedirect('/login');
    }
}