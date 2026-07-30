<?php

declare(strict_types=1);

/*
 * Temple Trust — Filesystem configuration.
 *
 * Doctrine (AGENTS.md §File Storage):
 *   "Large files should never be embedded inside PostgreSQL.
 *    The database stores metadata describing uploaded files.
 *    Laravel Storage manages physical file persistence."
 *
 * Two disks cover the entire asset surface for this project:
 *
 *   local  — private disk. Receipts (PDFs), 80G certificates, and any
 *            other private documents. Not web-reachable. Root:
 *            storage_path('app'). Default for receipt persistence
 *            per config/receipts.php.
 *
 *   public — web-reachable disk for CMS-rendered public media (hero
 *            banners, story / program / values / trustee images, etc.).
 *            Served through the /media/{id} route (Public/CmsMedia/
 *            ShowController) which adds ETag + Cache-Control headers.
 *            Visibility is set to 'public' as defense-in-depth — the
 *            primary access gate is state='published' AND archived_at IS NULL
 *            enforced in PublicMediaQuery, NOT the disk visibility.
 *            Root: storage_path('app/public').
 *
 * The `links` map materializes `public/storage` → `storage/app/public`
 * via `php artisan storage:link`, so any direct browser hit on
 * /storage/cms-media/... returns the file (still subject to the route's
 * access gate at the application layer).
 *
 * Cloud disks (S3, etc.) are deliberately NOT registered here. When the
 * project moves to object storage in production, add them under 'disks'
 * and switch config('filesystems.default') via env. The ShowController
 * reads the disk name straight from `file_assets.storage_disk` so any
 * registered disk will Just Work.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | The default disk is used when no disk name is passed to a Storage
    | call. The Receipt pipeline defaults to 'local' (private) — see
    | config/receipts.php. The CMS media pipeline passes the disk name
    | explicitly from file_assets.storage_disk and does not rely on this.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver'     => 'local',
            'root'       => storage_path('app'),
            'throw'      => false,
            'report'     => false,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw'      => false,
            'report'     => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Run `php artisan storage:link` to create public/storage →
    | storage/app/public. The ShowController at
    | app/Http/Controllers/Public/CmsMedia/ShowController.php:21 uses
    | response()->file($disk->path(...)) which does NOT depend on the
    | symlink — it reads the absolute filesystem path. The symlink is
    | here so any direct hit on /storage/cms-media/... resolves.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];