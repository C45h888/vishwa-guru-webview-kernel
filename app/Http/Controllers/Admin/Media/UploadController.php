<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Media;

use App\Campaigns\Services\CampaignCoverUploadService;
use App\Campaigns\Services\InvalidCoverImageException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin\Media\UploadController — accepts cover image uploads from the
 * admin campaigns authoring surface.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - The endpoint is Inertia-AJAX only (returns JSON, never HTML).
 *     The Svelte admin form uses fetch() with the Inertia XHR adapter
 *     to POST the file and read the response.
 *   - Auth is enforced by the route group's [web, auth, admin]
 *     middleware pipeline (see routes/admin.php). Anonymous or
 *     non-admin callers are redirected / 403'd before reaching the
 *     controller.
 *   - Validation is delegated to CampaignCoverUploadService — the
 *     controller only translates between HTTP and the service
 *     contract.
 *
 * @see \App\Campaigns\Services\CampaignCoverUploadService
 */
final class UploadController extends Controller
{
    public function __construct(
        private readonly CampaignCoverUploadService $uploads,
    ) {
    }

    /**
     * POST /admin/media/upload
     *
     * Body: multipart/form-data with a `file` field.
     * Response: { id: <ULID>, url: <string> } on success,
     *           { errors: { file: <message> } } on validation failure.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120'], // 5 MB
        ]);

        $userId = (string) ($request->user()?->getKey() ?? 'unknown');
        $file = $request->file('file');

        try {
            $fileAssetId = $this->uploads->upload($file, $userId);
        } catch (InvalidCoverImageException $e) {
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
