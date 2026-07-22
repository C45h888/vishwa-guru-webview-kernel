<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\UrlResolution;

use App\Cms\Contracts\ImageUrlResolverContract;
use App\Persistence\ValueObjects\EntityId;

final class PublicMediaUrlResolver implements ImageUrlResolverContract
{
    public function resolve(EntityId $fileId): string
    {
        return route('cms.public-media.show', ['id' => $fileId->value()]);
    }
}