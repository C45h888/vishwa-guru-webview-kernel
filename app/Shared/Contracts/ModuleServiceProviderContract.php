<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Marker interface for all domain module service providers.
 * Each module registers its own services via a ModuleServiceProvider.
 */
interface ModuleServiceProviderContract
{
    /**
     * Return the fully qualified module namespace.
     */
    public function moduleNamespace(): string;

    /**
     * Return the base path to the module directory.
     */
    public function modulePath(): string;
}
