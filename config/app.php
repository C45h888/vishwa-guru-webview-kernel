<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name & Environment
    |--------------------------------------------------------------------------
    */

    'name'      => env('APP_NAME', 'Temple Trust'),
    'env'       => env('APP_ENV', 'production'),
    'debug'     => (bool) env('APP_DEBUG', false),
    'url'       => env('APP_URL', 'http://localhost'),
    'asset_url' => env('ASSET_URL'),
    'timezone'  => env('APP_TIMEZONE', 'Asia/Kolkata'),
    'locale'    => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale'    => env('APP_FAKER_LOCALE', 'en_IN'),
    'key'       => env('APP_KEY'),
    'cipher'    => 'AES-256-CBC',

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on every
    | request to the application. The Shared module provider is required for
    | every other module to access ConfigurationContract, EnvironmentContract,
    | Clock, IdentifierGenerator, and ConfigurationRegistry.
    |
    */

    /*
    | The full app service-provider list lives in bootstrap/providers.php.
    | This file only contributes Laravel-internal defaults (auth, queue,
    | cache, ...) via ServiceProvider::defaultProviders(). Edit the
    | canonical file when registering or reordering app kernels.
    | See bootstrap/providers.php for the boot-order invariants and the
    | reasoning behind each provider's position in the array.
    */
    'providers' => ServiceProvider::defaultProviders()->merge(
        require __DIR__.'/../bootstrap/providers.php'
    )->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    */

    'aliases' => Facade::defaultAliases()->merge([
        // Custom aliases registered here.
    ])->toArray(),

];