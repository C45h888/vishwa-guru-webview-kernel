<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable value object mirroring the file_assets table row.
 *
 * @property string      $id
 * @property string      $ownerType       file_owner_type enum: receipt | certificate_80g | avatar | donation_proof
 * @property string      $ownerId         ULID of the owning entity
 * @property string      $originalFilename
 * @property string      $storageDisk     e.g. 'local', 's3'
 * @property string      $storagePath     relative path within the disk
 * @property string      $mimeType
 * @property int         $fileSizeBytes
 * @property string      $fileHashSha256  SHA-256 hex (64 chars)
 * @property string|null $purpose         purpose tag (mirrors schema column name)
 * @property bool        $isPublic
 * @property bool        $isArchived
 * @property DateTimeImmutable|null $archivedAt
 * @property array       $metadata         JSONB decoded
 * @property DateTimeImmutable $uploadedAt
 * @property DateTimeImmutable $createdAt
 * @property DateTimeImmutable $updatedAt
 */
final readonly class FileAssetRecord
{
    private const VALID_OWNER_TYPES = [
        'receipt',
        'certificate_80g',
        'avatar',
        'donation_proof',
    ];

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        private string $id,
        private string $ownerType,
        private string $ownerId,
        private string $originalFilename,
        private string $storageDisk,
        private string $storagePath,
        private string $mimeType,
        private int $fileSizeBytes,
        private string $fileHashSha256,
        private ?string $purpose = null,
        private bool $isPublic = false,
        private bool $isArchived = false,
        private ?DateTimeImmutable $archivedAt = null,
        private array $metadata = [],
        private DateTimeImmutable $uploadedAt,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Factory from constructor arguments.
     */
    public static function create(
        string $id,
        string $ownerType,
        string $ownerId,
        string $originalFilename,
        string $storageDisk,
        string $storagePath,
        string $mimeType,
        int $fileSizeBytes,
        string $fileHashSha256,
        ?string $purpose = null,
        bool $isPublic = false,
    ): self {
        self::validate($id, $ownerType, $fileSizeBytes, $fileHashSha256, $mimeType);

        $now = new DateTimeImmutable();

        return new self(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            originalFilename: $originalFilename,
            storageDisk: $storageDisk,
            storagePath: $storagePath,
            mimeType: $mimeType,
            fileSizeBytes: $fileSizeBytes,
            fileHashSha256: $fileHashSha256,
            purpose: $purpose,
            isPublic: $isPublic,
            isArchived: false,
            archivedAt: null,
            metadata: [],
            uploadedAt: $now,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * Rehydrate from a database row.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = [
            'id', 'owner_type', 'owner_id', 'original_filename',
            'storage_disk', 'storage_path', 'mime_type', 'file_size_bytes',
            'file_hash_sha256', 'uploaded_at', 'created_at', 'updated_at',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("FileAssetRecord row missing required key: {$key}");
            }
        }

        return new self(
            id: (string) $row['id'],
            ownerType: (string) $row['owner_type'],
            ownerId: (string) $row['owner_id'],
            originalFilename: (string) $row['original_filename'],
            storageDisk: (string) $row['storage_disk'],
            storagePath: (string) $row['storage_path'],
            mimeType: (string) $row['mime_type'],
            fileSizeBytes: (int) $row['file_size_bytes'],
            fileHashSha256: (string) $row['file_hash_sha256'],
            purpose: isset($row['purpose']) ? (string) $row['purpose'] : null,
            isPublic: (bool) ($row['is_public'] ?? false),
            isArchived: (bool) ($row['is_archived'] ?? false),
            archivedAt: isset($row['archived_at']) && $row['archived_at'] !== null
                ? new DateTimeImmutable((string) $row['archived_at'])
                : null,
            metadata: isset($row['metadata']) && is_string($row['metadata'])
                ? (json_decode($row['metadata'], true) ?? [])
                : (is_array($row['metadata']) ? $row['metadata'] : []),
            uploadedAt: new DateTimeImmutable((string) $row['uploaded_at']),
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
        );
    }

    private static function validate(
        string $id,
        string $ownerType,
        int $fileSizeBytes,
        string $fileHashSha256,
        string $mimeType,
    ): void {
        if ($id === '') {
            throw new InvalidArgumentException('FileAssetRecord id cannot be empty');
        }
        if (! in_array($ownerType, self::VALID_OWNER_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'FileAssetRecord ownerType must be one of [%s]; got "%s"',
                    implode(', ', self::VALID_OWNER_TYPES),
                    $ownerType,
                ),
            );
        }
        if ($fileSizeBytes <= 0) {
            throw new InvalidArgumentException(
                "FileAssetRecord fileSizeBytes must be positive (got {$fileSizeBytes})",
            );
        }
        if (! preg_match('/^[a-f0-9]{64}$/', $fileHashSha256)) {
            throw new InvalidArgumentException(
                "FileAssetRecord fileHashSha256 must be a 64-char hex string (SHA-256)",
            );
        }
        if ($mimeType === '') {
            throw new InvalidArgumentException('FileAssetRecord mimeType cannot be empty');
        }
    }

    // ─── Getters ─────────────────────────────────────────────────────────

    public function id(): string { return $this->id; }
    public function ownerType(): string { return $this->ownerType; }
    public function ownerId(): string { return $this->ownerId; }
    public function originalFilename(): string { return $this->originalFilename; }
    public function storageDisk(): string { return $this->storageDisk; }
    public function storagePath(): string { return $this->storagePath; }
    public function mimeType(): string { return $this->mimeType; }
    public function fileSizeBytes(): int { return $this->fileSizeBytes; }
    public function fileHashSha256(): string { return $this->fileHashSha256; }
    public function purpose(): ?string { return $this->purpose; }
    public function isPublic(): bool { return $this->isPublic; }
    public function isArchived(): bool { return $this->isArchived; }
    public function archivedAt(): ?DateTimeImmutable { return $this->archivedAt; }
    public function metadata(): array { return $this->metadata; }
    public function uploadedAt(): DateTimeImmutable { return $this->uploadedAt; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'owner_type' => $this->ownerType,
            'owner_id' => $this->ownerId,
            'original_filename' => $this->originalFilename,
            'storage_disk' => $this->storageDisk,
            'storage_path' => $this->storagePath,
            'mime_type' => $this->mimeType,
            'file_size_bytes' => $this->fileSizeBytes,
            'file_hash_sha256' => $this->fileHashSha256,
            'purpose' => $this->purpose,
            'is_public' => $this->isPublic,
            'is_archived' => $this->isArchived,
            'archived_at' => $this->archivedAt?->format(DATE_ATOM),
            'metadata' => $this->metadata,
            'uploaded_at' => $this->uploadedAt->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
