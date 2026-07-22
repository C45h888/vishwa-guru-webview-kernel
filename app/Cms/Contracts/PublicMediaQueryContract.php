<?php

declare(strict_types=1);

namespace App\Cms\Contracts;

use App\Cms\Domain\DTOs\PublicMediaProjection;

interface PublicMediaQueryContract
{
    public function find(string $id): ?PublicMediaProjection;

    /**
     * @param list<string> $ids
     * @return array<string, PublicMediaProjection>
     */
    public function findMany(array $ids): array;
}