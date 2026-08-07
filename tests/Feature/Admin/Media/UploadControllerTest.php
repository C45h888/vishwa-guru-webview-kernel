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
 *   - A `purpose` discriminator selects the owning service so event
 *     banners are stored as owner_type='event_cover' under
 *     event-banners/ (Pass 3), NOT mislabelled as campaign covers.
 *   - purpose='campaign_cover'  → CampaignCoverUploadService
 *   - purpose='event_cover'     → EventBannerUploadService
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
    public function test_campaign_cover_purpose_stores_as_campaign_cover(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/media/upload', [
                'file' => $this->validImage(),
                'purpose' => 'campaign_cover',
            ])
            ->assertCreated()
            ->assertJsonStructure(['id']);

        $row = DB::table('file_assets')->first();
        $this->assertNotNull($row);
        $this->assertSame('campaign_cover', $row->owner_type);
        $this->assertSame('campaign_cover', $row->purpose);
        $this->assertStringContainsString('campaign-covers/', $row->storage_path);
    }

    #[Test]
    public function test_event_cover_purpose_stores_as_event_cover(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post('/admin/media/upload', [
                'file' => $this->validImage(),
                'purpose' => 'event_cover',
            ])
            ->assertCreated()
            ->assertJsonStructure(['id']);

        $row = DB::table('file_assets')->first();
        $this->assertNotNull($row);
        $this->assertSame('event_cover', $row->owner_type);
        $this->assertSame('event_cover', $row->purpose);
        $this->assertStringContainsString('event-banners/', $row->storage_path);
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