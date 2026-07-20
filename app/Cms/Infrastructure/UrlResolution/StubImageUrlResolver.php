<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\UrlResolution;

use App\Cms\Contracts\ImageUrlResolverContract;
use App\Persistence\ValueObjects\EntityId;

/**
 * Stub implementation of ImageUrlResolverContract for V1.
 *
 * Returns the file id as a placeholder URL — Phase 4 replaces this with
 * a real file storage resolver (S3 / local). Pages with images will
 * display placeholder URLs until the real implementation lands.
 */
final class StubImageUrlResolver implements ImageUrlResolverContract
{
    public function resolve(EntityId $fileId): string
    {
        return "/placeholder/file/{$fileId->value()}";
    }
}