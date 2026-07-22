<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Contracts\PublicMediaQueryContract;

final class PublicMediaPresentationService
{
    public function __construct(private readonly PublicMediaQueryContract $media) {}

    /** @param array<string, mixed> $payload */
    public function enrich(array $payload, string $idKey, string $presentationKey): array
    {
        $id = $payload[$idKey] ?? null;
        $projection = is_string($id) && $id !== '' ? $this->media->find($id) : null;
        $payload[$presentationKey] = $projection?->toArray(
            route('cms.public-media.show', ['id' => $projection->id]),
        );
        if (is_array($payload[$presentationKey] ?? null)) {
            foreach (['alt_text', 'caption', 'credit'] as $field) {
                if (($payload[$presentationKey][$field] ?? null) === null && isset($payload[$field])) {
                    $payload[$presentationKey][$field] = $payload[$field];
                }
            }
        }

        return $payload;
    }

    /** @param list<array<string, mixed>> $items */
    public function enrichMany(array $items, string $idKey, string $presentationKey): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $item) => $item[$idKey] ?? null,
            $items,
        ), static fn ($id): bool => is_string($id) && $id !== ''));
        $projections = $this->media->findMany($ids);

        return array_map(
            function (array $item) use ($idKey, $presentationKey, $projections): array {
                $id = $item[$idKey] ?? null;
                $projection = is_string($id) ? ($projections[$id] ?? null) : null;
                $item[$presentationKey] = $projection?->toArray(
                    route('cms.public-media.show', ['id' => $projection->id]),
                );
                if (is_array($item[$presentationKey] ?? null)) {
                    foreach (['alt_text', 'caption', 'credit'] as $field) {
                        if (($item[$presentationKey][$field] ?? null) === null && isset($item[$field])) {
                            $item[$presentationKey][$field] = $item[$field];
                        }
                    }
                }
                return $item;
            },
            $items,
        );
    }

    /** @param array<string, mixed> $payload */
    public function enrichNestedMany(
        array $payload,
        string $itemsKey,
        string $idKey,
        string $presentationKey,
    ): array {
        $items = $payload[$itemsKey] ?? [];
        if (is_array($items)) {
            $payload[$itemsKey] = $this->enrichMany($items, $idKey, $presentationKey);
        }
        return $payload;
    }
}