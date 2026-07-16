<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Infrastructure\Adapters\InMemory\InMemoryGatewayAdapter;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalAdapter;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayAdapter;
use App\Payments\Providers\PaymentsServiceProvider;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Enums\EnvironmentType;
use App\Shared\Support\FrozenClock;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the env-driven branching in PaymentsServiceProvider:
 *
 *   APP_ENV=production   → only RazorpayAdapter + PayPalAdapter (if enabled)
 *   APP_ENV=local|test   → + InMemoryGatewayAdapter (if enabled)
 *
 * These are behavioural tests on the envAwareGatewayClasses logic,
 * exercised through the public service container rather than through
 * a private accessor.
 */
class EnvBindingMatrixTest extends TestCase
{
    private function buildApp(string $envValue): Application
    {
        // Force APP_ENV before the app boots so the env resolver picks it up
        putenv("APP_ENV={$envValue}");
        $_ENV['APP_ENV'] = $envValue;

        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    private function taggedGatewayClasses(Application $app): array
    {
        $gateways = iterator_to_array(
            $app->tagged(PaymentsServiceProvider::gatewayTag())
        );
        return array_map(static fn ($g) => $g::class, $gateways);
    }

    public function testLocalEnvironmentIncludesInMemoryGateway(): void
    {
        $app = $this->buildApp('local');
        $classes = $this->taggedGatewayClasses($app);

        $this->assertContains(InMemoryGatewayAdapter::class, $classes,
            'Local env must include InMemory gateway by default');
        $this->assertContains(RazorpayAdapter::class, $classes);
        $this->assertContains(PayPalAdapter::class, $classes);
        $this->assertCount(3, $classes, 'Local env should have exactly 3 gateways');
    }

    public function testTestingEnvironmentIncludesInMemoryGateway(): void
    {
        $app = $this->buildApp('testing');
        $classes = $this->taggedGatewayClasses($app);

        $this->assertContains(InMemoryGatewayAdapter::class, $classes);
        $this->assertContains(RazorpayAdapter::class, $classes);
        $this->assertContains(PayPalAdapter::class, $classes);
        $this->assertCount(3, $classes);
    }

    public function testProductionEnvironmentExcludesInMemory(): void
    {
        $app = $this->buildApp('production');
        $classes = $this->taggedGatewayClasses($app);

        $this->assertNotContains(InMemoryGatewayAdapter::class, $classes,
            'Production env must NEVER include InMemory gateway');
        $this->assertContains(RazorpayAdapter::class, $classes);
        $this->assertContains(PayPalAdapter::class, $classes);
        $this->assertCount(2, $classes, 'Production env should have exactly 2 gateways');
    }

    public function testDisabledProviderIsExcludedFromAllEnvs(): void
    {
        $app = $this->buildApp('local');
        // Disable PayPal via config
        $config = $app->make(ConfigurationContract::class);
        // We can't mutate a config contract after resolve, so we re-bootstrap
        putenv('PAYPAL_ENABLED=false');
        $_ENV['PAYPAL_ENABLED'] = 'false';
        $app = $this->buildApp('local');

        $classes = $this->taggedGatewayClasses($app);

        $this->assertNotContains(PayPalAdapter::class, $classes,
            'Disabled provider must never appear in the tagged pool');
        $this->assertContains(RazorpayAdapter::class, $classes);
        $this->assertContains(InMemoryGatewayAdapter::class, $classes);

        // Cleanup env
        putenv('PAYPAL_ENABLED');
        unset($_ENV['PAYPAL_ENABLED']);
    }

    public function testInMemoryDisabledInProductionAndLocal(): void
    {
        // When PAYMENTS_INMEMORY_ENABLED=false, even local env drops it
        putenv('PAYMENTS_INMEMORY_ENABLED=false');
        $_ENV['PAYMENTS_INMEMORY_ENABLED'] = 'false';
        $app = $this->buildApp('local');
        $classes = $this->taggedGatewayClasses($app);

        $this->assertNotContains(InMemoryGatewayAdapter::class, $classes);
        $this->assertCount(2, $classes);

        putenv('PAYMENTS_INMEMORY_ENABLED');
        unset($_ENV['PAYMENTS_INMEMORY_ENABLED']);
    }
}
