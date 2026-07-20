<?php

declare(strict_types=1);

namespace App\Cms\Contracts;

use App\Persistence\ValueObjects\EntityId;

/**
 * Resolves an ImageBlock's file id to a URL.
 *
 * V1 ships StubImageUrlResolver which returns the file id as a
 * placeholder URL. Phase 4 replaces this with a real file storage
 * implementation (S3 / local).
 */
interface ImageUrlResolverContract
{
    public function resolve(EntityId $fileId): string;
}