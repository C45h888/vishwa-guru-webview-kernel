<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects\Blocks;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * An image block — references a file asset (image) by id.
 *
 * The renderer resolves the file id to a URL via ImageUrlResolverContract.
 * V1's stub returns the file id as a placeholder; Phase 4 wires real
 * file storage.
 */
final readonly class ImageBlock implements Block
{
    public function __construct(
        public EntityId $fileId,
        public string $alt,
        public ?string $caption = null,
        public ?int $width = null,
        public ?int $height = null,
    ) {
        if (trim($alt) === '') {
            throw new InvalidArgumentException('ImageBlock alt text cannot be empty (accessibility requirement)');
        }
        if ($width !== null && $width <= 0) {
            throw new InvalidArgumentException("ImageBlock width must be positive (got {$width})");
        }
        if ($height !== null && $height <= 0) {
            throw new InvalidArgumentException("ImageBlock height must be positive (got {$height})");
        }
    }

    public function type(): string
    {
        return 'image';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'file_id' => $this->fileId->value(),
            'alt' => $this->alt,
            'caption' => $this->caption,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}