<?php

declare(strict_types=1);

namespace App\Shared\Configuration;

use App\Shared\Contracts\ConfigurationContract;

/**
 * Registry of ConfigurationContract instances, indexed by namespace.
 *
 * Lets the application access configuration across logical
 * sub-systems (shared, app, payments, donations, etc.) without
 * coupling the consumer to Laravel's config() helper.
 *
 * Each namespace resolves to a contract that prefixes all keys
 * with the namespace, e.g.:
 *
 *   $registry->namespace('app')->string('env')
 *     === config('app.env')
 */
final class ConfigurationRegistry
{
    /**
     * @var array<string, ConfigurationContract>
     */
    private array $contracts = [];

    public function __construct()
    {
        // Default contracts: both namespaced to the empty root
        // (no prefix added).
        $this->contracts['shared'] = new NamespacedConfiguration('');
        $this->contracts['app'] = new NamespacedConfiguration('app');
    }

    /**
     * Register a configuration contract for a namespace.
     */
    public function register(string $namespace, ConfigurationContract $contract): void
    {
        $this->contracts[$namespace] = $contract;
    }

    /**
     * Get a configuration contract for the given namespace.
     */
    public function namespace(string $name): ConfigurationContract
    {
        if (! isset($this->contracts[$name])) {
            throw new \InvalidArgumentException(
                "No configuration registered for namespace [{$name}]"
            );
        }

        return $this->contracts[$name];
    }

    /**
     * Whether a namespace has a registered configuration contract.
     */
    public function has(string $namespace): bool
    {
        return isset($this->contracts[$namespace]);
    }

    /**
     * List all registered namespaces.
     *
     * @return array<int, string>
     */
    public function namespaces(): array
    {
        return array_keys($this->contracts);
    }
}
