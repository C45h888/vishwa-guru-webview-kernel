<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Base contract for all service classes.
 * Services implement business workflows.
 * Controllers must remain thin and delegate to services.
 */
interface ServiceContract
{
    /**
     * Determine if the service is ready to operate.
     */
    public function isReady(): bool;
}
