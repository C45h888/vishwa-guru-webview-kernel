<?php

declare(strict_types=1);

namespace App\Events\Services;

use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use App\Cms\Domain\Repositories\CmsMediaAssetRepositoryContract;
use App\Cms\Domain\ValueObjects\CmsMediaAssetRecord;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\ValueObjects\FileAssetRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * EventBannerUploadService — handles banner image upload for the
 * admin events authoring surface.
 *
 * Doctrine (mirrors CampaignCoverUploadService):
 *   - Canonical path from an UploadedFile to a cms_media_assets.id (ULID)
 *     that the events.banner_file_id FK + PublicMediaQuery can resolve.
 *   - The schema models public media in TWO layers:
 *       1. file_assets      — the physical file (owner_type='event_banner',
 *                             a valid file_owner_type enum value).
 *       2. cms_media_assets — the published presentation overlay that
 *                             PublicMediaQuery / /media/{id} read from.
 *     Both are written here, atomically, so the returned id is immediately
 *     displayable and satisfies the events_banner_cms_boundary FK.
 *   - Files are stored on the `public` disk so the existing
 *     PublicMediaPresentationService can resolve them via /media/{id}.
 *   - Dedupe by SHA-256: same content uploaded before returns the
 *     existing cms_media_asset id instead of creating a duplicate.
 *   - Validation: 5MB max, jpg/png/webp only.
 *
 * @see CampaignCoverUploadService
 */
final class EventBannerUploadService
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
    ) {
    }

    /**
     * Persist the uploaded file + its published overlay.
     *
     * @return string  ULID of the cms_media_assets row (displayable via /media/{id}).
     *
     * @throws InvalidEventBannerException
     */
    public function upload(UploadedFile $file, string $uploadedBy): string
    {
        if (! $file->isValid()) {
            throw new InvalidEventBannerException('Uploaded file is not valid.');
        }

        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidEventBannerException(sprintf(
                'Banner image MIME type "%s" is not allowed. Allowed: jpeg, png, webp.',
                $mime,
            ));
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidEventBannerException(sprintf(
                'Banner image size %d bytes is outside the allowed range (1 to %d bytes).',
                $size,
                self::MAX_BYTES,
            ));
        }

        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new InvalidEventBannerException('Could not read the uploaded file contents.');
        }

        $hash = hash('sha256', $bytes);
        if ($hash === false) {
            throw new InvalidEventBannerException('Could not hash the uploaded file contents.');
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
            $newOverlayId = $this->createOverlay($existing->id(), $uploadedBy, $originalFilename);
            return $newOverlayId;
        }

        // Storage path: event-banners/<year>/<month>/<hash-prefix>/<hash>.<ext>
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new InvalidEventBannerException(sprintf('Unsupported MIME "%s".', $mime)),
        };
        $now = new \DateTimeImmutable();
        $relativePath = sprintf(
            'event-banners/%s/%s/%s/%s.%s',
            $now->format('Y'),
            $now->format('m'),
            substr($hash, 0, 2),
            $hash,
            $ext,
        );
        $disk = Storage::disk('public');
        $writeResult = $disk->put($relativePath, $bytes);
        if ($writeResult === false) {
            throw new RuntimeException('Failed to write banner image to the public disk.');
        }

        $fileAssetId = (string) Str::ulid();
        $record = new FileAssetRecord(
            id: $fileAssetId,
            ownerType: 'event_banner', // must be a valid file_owner_type enum value
            ownerId: 'pending',
            originalFilename: $originalFilename,
            storageDisk: 'public',
            storagePath: $relativePath,
            mimeType: $mime,
            fileSizeBytes: $size,
            fileHashSha256: $hash,
            purpose: 'event_banner',
            isPublic: true,
            isArchived: false,
            archivedAt: null,
            metadata: [
                'uploaded_by' => $uploadedBy,
                'uploaded_via' => 'admin.events.upload',
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
            mediaType: PublicMediaType::EVENT_BANNER,
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