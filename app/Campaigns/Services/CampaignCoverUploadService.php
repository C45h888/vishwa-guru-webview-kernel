<?php

declare(strict_types=1);

namespace App\Campaigns\Services;

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
 *     file_asset.id (a ULID string). Admin controllers depend on
 *     this service, NEVER on raw Storage::put() or direct INSERTs.
 *   - Files are stored on the `public` disk so the existing
 *     PublicMediaPresentationService can resolve them via the
 *     `/media/{id}` route without an additional auth hop.
 *   - We dedupe by SHA-256: if the same content was uploaded before,
 *     the existing file_asset row is returned (no second upload,
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
        private readonly PersistenceAdapterContract $persistence,
    ) {
    }

    /**
     * Persist the uploaded file and return the resulting file_asset.id.
     *
     * @return string  ULID of the file_asset row.
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

        // Dedupe: if a file_asset with this hash already exists, reuse it.
        $existing = $this->fileAssets->findByHash($hash);
        if ($existing !== null) {
            return $existing->id;
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

        // Create the file_asset row.
        $record = new FileAssetRecord(
            id: (string) Str::ulid(),
            ownerType: 'campaign_cover',
            ownerId: 'pending',   // resolved when the campaign is created/updated
            originalFilename: (string) $file->getClientOriginalName(),
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

        return $record->id();
    }
}
