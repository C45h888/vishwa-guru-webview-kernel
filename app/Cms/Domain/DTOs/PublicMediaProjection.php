<?php

declare(strict_types=1);

namespace App\Cms\Domain\DTOs;

use InvalidArgumentException;

final readonly class PublicMediaProjection
{
    public function __construct(
        public string $id,
        public string $storageDisk,
        public string $storagePath,
        public string $mimeType,
        public int $fileSizeBytes,
        public string $contentHash,
        public bool $isPublic,
        public bool $isArchived,
        public ?string $altText = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $caption = null,
        public ?string $credit = null,
    ) {
        if ($id === '' || $storageDisk === '' || $storagePath === '') {
            throw new InvalidArgumentException('Public media identity and storage coordinates are required');
        }
        if ($fileSizeBytes < 0) {
            throw new InvalidArgumentException('Public media file size cannot be negative');
        }
    }

    public function isDisplayable(): bool
    {
        return $this->isPublic && ! $this->isArchived;
    }

    /** @return array<string, mixed> */
    public function toArray(string $url): array
    {
        return [
            'id' => $this->id,
            'url' => $url,
            'mime_type' => $this->mimeType,
            'file_size_bytes' => $this->fileSizeBytes,
            'content_hash' => $this->contentHash,
            'alt_text' => $this->altText,
            'width' => $this->width,
            'height' => $this->height,
            'caption' => $this->caption,
            'credit' => $this->credit,
        ];
    }
}