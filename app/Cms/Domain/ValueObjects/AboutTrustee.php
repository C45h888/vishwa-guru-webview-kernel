<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * A single trustee/leadership entry of `static_pages.about_page_content.trustees`.
 *
 * Each trustee has a name, role, optional photo reference (validated by
 * AboutPageContent against cms_media_assets), and an optional bio. The
 * trustee is the only About section that requires a list-of-N shape in
 * V1; the Svelte layer renders a 3-column responsive grid.
 */
final readonly class AboutTrustee
{
    public function __construct(
        private string $name,
        private string $role,
        private ?EntityId $photoFileId,
        private ?string $bio,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('AboutTrustee name cannot be empty');
        }
        if (trim($role) === '') {
            throw new InvalidArgumentException('AboutTrustee role cannot be empty');
        }
        if ($bio !== null && trim($bio) === '') {
            throw new InvalidArgumentException('AboutTrustee bio must be null or non-empty');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function role(): string
    {
        return $this->role;
    }

    public function photoFileId(): ?EntityId
    {
        return $this->photoFileId;
    }

    public function bio(): ?string
    {
        return $this->bio;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'role' => $this->role,
            'photo_file_id' => $this->photoFileId?->value(),
            'bio' => $this->bio,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $photoFileId = null;
        if (isset($row['photo_file_id']) && $row['photo_file_id'] !== null && $row['photo_file_id'] !== '') {
            $photoFileId = EntityId::fromString((string) $row['photo_file_id']);
        }

        return new self(
            name: (string) ($row['name'] ?? ''),
            role: (string) ($row['role'] ?? ''),
            photoFileId: $photoFileId,
            bio: isset($row['bio']) ? (string) $row['bio'] : null,
        );
    }
}
