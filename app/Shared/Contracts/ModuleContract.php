<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Contract every business module must satisfy.
 *
 * A Module is a self-contained business capability (Donations, Payments,
 * CMS, ...) that owns its own services, repositories, requests,
 * controllers, policies, and resources. Modules communicate exclusively
 * through service contracts — never through direct internal manipulation.
 *
 * The Module contract is the enforcement point for module discovery:
 * the Shared module uses it to validate that a registered module
 * declares its dependencies and ownership boundaries honestly.
 *
 * Note: this contract previously declared a `boot(): void` hook that
 * was never invoked by any Service Provider — kernel wiring happens
 * directly in the `boot()` method of each *ServiceProvider. The hook
 * was removed; the actual boot-order invariants are pinned by
 * tests/Unit/Bootstrap/ProviderOrderTest.php.
 */
interface ModuleContract
{
    /**
     * The unique business identifier of the module.
     *
     * Examples: "donations", "payments", "cms", "gallery", "events".
     */
    public function name(): string;

    /**
     * The list of module names this module depends on.
     *
     * Dependencies always flow toward the Shared module. Modules MUST NOT
     * declare dependencies on other modules unless those dependencies
     * are sanctioned by the architecture document (modules.md).
     *
     * @return list<class-string>
     */
    public function dependencies(): array;
}
