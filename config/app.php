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

    'providers' => ServiceProvider::defaultProviders()->merge([
        App\Providers\AppServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Shared\Providers\SharedServiceProvider::class,
        App\Persistence\Providers\PersistenceServiceProvider::class,
        App\Runtime\Providers\RuntimeServiceProvider::class,
        App\Redis\Providers\RedisServiceProvider::class,
        // QueueServiceProvider — wires QueueConnectorContract → LaravelQueueConnector.
        // Doctrine: service code depends on QueueConnectorContract, NEVER on
        // Illuminate\Support\Facades\Queue. Laravel's built-in QueueServiceProvider
        // is loaded via defaultProviders() above (provides Queue\Factory); ours
        // adds the Queue module's typed contract on top.
        App\Queue\Providers\QueueServiceProvider::class,
        App\Payments\Providers\PaymentsServiceProvider::class,
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    */

    'aliases' => Facade::defaultAliases()->merge([
        // Custom aliases registered here.
    ])->toArray(),

];