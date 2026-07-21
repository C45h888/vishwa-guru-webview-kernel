<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Vite 5 emits the manifest at build/.vite/manifest.json by default;
        // Laravel's @vite() helper looks for build/manifest.json. Tell it
        // where to find the actual file.
        Vite::useManifestFilename('.vite/manifest.json');
    }
}
