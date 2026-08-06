<?php

declare(strict_types=1);

namespace App\Events\Services;

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
 *   - Canonical path from an UploadedFile to a file_asset.id (ULID).
 *   - Files are stored on the `public` disk so the existing
 *     PublicMediaPresentationService can resolve them via /media/{id}.
 *   - Dedupe by SHA-256: same content uploaded before returns the
 *     existing file_asset row instead of creating a duplicate.
 *   - Validation: 5MB max, jpg/png/webp only.
 *   - owner_type='event_cover' (already in FileAssetRecord::VALID_OWNER_TYPES).
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
    ) {
    }

    /**
     * @return string  ULID of the file_asset row.
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

        // Dedupe: if a file_asset with this hash already exists, reuse it.
        $existing = $this->fileAssets->findByHash($hash);
        if ($existing !== null) {
            return $existing->id;
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

        $record = new FileAssetRecord(
            id: (string) Str::ulid(),
            ownerType: 'event_cover',
            ownerId: 'pending',
            originalFilename: (string) $file->getClientOriginalName(),
            storageDisk: 'public',
            storagePath: $relativePath,
            mimeType: $mime,
            fileSizeBytes: $size,
            fileHashSha256: $hash,
            purpose: 'event_cover',
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

        return $record->id;
    }
}
