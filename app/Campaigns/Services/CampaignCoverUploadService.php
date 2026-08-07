<?php

declare(strict_types=1);

namespace App\Campaigns\Services;

use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use App\Cms\Domain\Repositories\CmsMediaAssetRepositoryContract;
use App\Cms\Domain\ValueObjects\CmsMediaAssetRecord;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\ValueObjects\FileAssetRecord;
use App\Persistence\Contracts\PersistenceAdapterContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * CampaignCoverUploadService — handles cover image upload for the
 * admin campaigns authoring surface.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - The service is the canonical path from an UploadedFile to a
 *     cms_media_assets.id that the campaigns.cover_image_file_id FK +
 *     PublicMediaQuery can resolve. Admin controllers depend on this
 *     service, NEVER on raw Storage::put() or direct INSERTs.
 *   - The schema models public media in TWO layers:
 *       1. file_assets      — the physical file (owner_type='campaign_cover',
 *                             a valid file_owner_type enum value).
 *       2. cms_media_assets — the published presentation overlay that
 *                             PublicMediaQuery / /media/{id} read from and
 *                             that campaigns.cover_image_file_id FK references.
 *     Both are written here so the returned id is immediately displayable
 *     and satisfies the campaigns_cover_cms_boundary FK.
 *   - Files are stored on the `public` disk so the existing
 *     PublicMediaPresentationService can resolve them via `/media/{id}`
 *     without an additional auth hop.
 *   - We dedupe by SHA-256: if the same content was uploaded before,
 *     the existing cms_media_asset id is returned (no second upload,
 *     no second physical file).
 *   - Validation: max 5MB; allowed MIME types = image/jpeg,
 *     image/png, image/webp. Anything else throws InvalidCoverImageException.
 *   - The service goes through PersistenceAdapterContract for the
 *     INSERT path (not DB:: facade), matching the Payments kernel's
 *     FileAssetRepository contract.
 *
 * @see \App\Payments\Domain\ValueObjects\FileAssetRecord
 */
final class CampaignCoverUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const MAX_BYTES = 5 * 1024 * 1024; // 5MB

    public function __construct(
        private readonly FileAssetRepositoryContract $fileAssets,
        private readonly CmsMediaAssetRepositoryContract $mediaAssets,
        private readonly PersistenceAdapterContract $persistence,
    ) {
    }

    /**
     * Persist the uploaded file + its published overlay.
     *
     * @return string  ULID of the cms_media_assets row (displayable via /media/{id}).
     *
     * @throws InvalidCoverImageException
     */
    public function upload(UploadedFile $file, string $uploadedBy): string
    {
        if (! $file->isValid()) {
            throw new InvalidCoverImageException('Uploaded file is not valid.');
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidCoverImageException(sprintf(
                'Cover image MIME type "%s" is not allowed. Allowed: jpeg, png, webp.',
                $mime,
            ));
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidCoverImageException(sprintf(
                'Cover image size %d bytes is outside the allowed range (1 to %d bytes).',
                $size,
                self::MAX_BYTES,
            ));
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new InvalidCoverImageException('Could not read the uploaded file contents.');
        }

        $hash = hash('sha256', $bytes);
        if ($hash === false) {
            throw new InvalidCoverImageException('Could not hash the uploaded file contents.');
        }

        $originalFilename = (string) $file->getClientOriginalName();

        // Dedupe: if a file_asset with this hash already exists, reuse it.
        // Return the existing cms_media_asset id (creating the overlay if a
        // legacy file_asset exists without one).
        $existing = $this->fileAssets->findByHash($hash);
        if ($existing !== null) {
            $overlay = $this->mediaAssets->findByFileAssetId($existing->id());
            if ($overlay !== null) {
                return $overlay->id();
            }
            return $this->createOverlay($existing->id(), $uploadedBy, $originalFilename);
        }

        // Persist the physical file. Storage path layout:
        //   campaign-covers/<year>/<month>/<hash-prefix>/<hash>.<ext>
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new InvalidCoverImageException(sprintf('Unsupported MIME "%s".', $mime)),
        };
        $now = new \DateTimeImmutable();
        $relativePath = sprintf(
            'campaign-covers/%s/%s/%s/%s.%s',
            $now->format('Y'),
            $now->format('m'),
            substr($hash, 0, 2),
            $hash,
            $ext,
        );
        $disk = Storage::disk('public');
        $writeResult = $disk->put($relativePath, $bytes);
        if ($writeResult === false) {
            throw new RuntimeException('Failed to write cover image to the public disk.');
        }

        // Create the file_assets row.
        $fileAssetId = (string) Str::ulid();
        $record = new FileAssetRecord(
            id: $fileAssetId,
            ownerType: 'campaign_cover', // must be a valid file_owner_type enum value
            ownerId: 'pending',   // resolved when the campaign is created/updated
            originalFilename: $originalFilename,
            storageDisk: 'public',
            storagePath: $relativePath,
            mimeType: $mime,
            fileSizeBytes: $size,
            fileHashSha256: $hash,
            purpose: 'campaign_cover',
            isPublic: true,
            isArchived: false,
            archivedAt: null,
            metadata: [
                'uploaded_by' => $uploadedBy,
                'uploaded_via' => 'admin.campaigns.upload',
            ],
            uploadedAt: $now,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->fileAssets->save($record);

        return $this->createOverlay($fileAssetId, $uploadedBy, $originalFilename);
    }

    /**
     * Create the published cms_media_assets overlay row for the file_asset.
     * A published asset requires alt_text + published_at (CHECK constraint).
     * We default alt_text to the original filename; the admin can refine it
     * later via the CMS editing surface.
     *
     * @return string  cms_media_assets.id
     */
    private function createOverlay(string $fileAssetId, string $uploadedBy, string $altTextDefault): string
    {
        $id = (string) Str::ulid();
        $now = new \DateTimeImmutable();

        $overlay = CmsMediaAssetRecord::create(
            id: $id,
            fileAssetId: $fileAssetId,
            mediaType: PublicMediaType::CAMPAIGN_COVER,
            state: PublicMediaState::PUBLISHED,
            altText: $altTextDefault,
            caption: null,
            credit: null,
            width: null,
            height: null,
            focalX: null,
            focalY: null,
            variantGroupId: null,
            publishedAt: $now,
            archivedAt: null,
            createdBy: $uploadedBy,
            updatedBy: $uploadedBy,
        );

        $this->mediaAssets->save($overlay);

        return $id;
    }
}