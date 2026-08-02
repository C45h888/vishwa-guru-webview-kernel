<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\CmsMedia;

use App\Cms\Contracts\PublicMediaQueryContract;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Storage;

final class ShowController
{
    public function __invoke(string $id, PublicMediaQueryContract $media): Response|BinaryFileResponse
    {
        $projection = $media->find($id);
        abort_if($projection === null || ! $projection->isDisplayable(), 404);

        $disk = Storage::disk($projection->storageDisk);
        abort_unless($disk->exists($projection->storagePath), 404);

        return response()->file($disk->path($projection->storagePath), [
            'Content-Type' => $projection->mimeType,
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => '"'.$projection->contentHash.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}