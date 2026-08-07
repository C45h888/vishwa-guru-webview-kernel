<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Media;

use App\Campaigns\Services\CampaignCoverUploadService;
use App\Events\Services\EventBannerUploadService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Admin\Media\UploadController — accepts cover image uploads from the
 * admin campaigns and events authoring surfaces.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - The endpoint is Inertia-AJAX only (returns JSON, never HTML).
 *     The Svelte admin form uses XMLHttpRequest to POST the file and
 *     read the response.
 *   - Auth is enforced by the route group's [web, auth, admin]
 *     middleware pipeline (see routes/admin.php). Anonymous or
 *     non-admin callers are redirected / 403'd before reaching the
 *     controller.
 *   - A `purpose` discriminator in the request body selects the
 *     correct owner_type + storage path:
 *       purpose='campaign_cover' → CampaignCoverUploadService
 *       purpose='event_cover'    → EventBannerUploadService
 *     This is what keeps event banners in `event-banners/` with
 *     owner_type='event_cover' (Pass 3) instead of being mislabelled
 *     as campaign covers.
 *   - Validation is delegated to the upload services — the controller
 *     only translates between HTTP and the service contract.
 *
 * @see \App\Campaigns\Services\CampaignCoverUploadService
 * @see \App\Events\Services\EventBannerUploadService
 */
final class UploadController extends Controller
{
    public function __construct(
        private readonly CampaignCoverUploadService $campaignUploads,
        private readonly EventBannerUploadService $eventUploads,
    ) {
    }

    /**
     * POST /admin/media/upload
     *
     * Body: multipart/form-data with a `file` field and a `purpose`
     * discriminator ('campaign_cover' | 'event_cover').
     * Response: { id: <ULID> } on success,
     *           { errors: { file: <message> } } on validation failure.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'], // 5 MB
            'purpose' => ['required', 'string', 'in:campaign_cover,event_cover'],
        ]);

        $userId = (string) ($request->user()?->getKey() ?? 'unknown');
        $file = $request->file('file');
        $purpose = (string) $request->input('purpose');

        try {
            $fileAssetId = $purpose === 'event_cover'
                ? $this->eventUploads->upload($file, $userId)
                : $this->campaignUploads->upload($file, $userId);
        } catch (RuntimeException $e) {
            return response()->json([
                'errors' => [
                    'file' => [$e->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'id' => $fileAssetId,
            // URL is computed by the client from the file_asset_id
            // via the existing /media/{id} route. We return id alone
            // to keep this endpoint domain-agnostic.
        ], 201);
    }
}