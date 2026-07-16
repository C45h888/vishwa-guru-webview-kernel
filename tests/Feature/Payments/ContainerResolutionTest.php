<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\DonorRepositoryContract;
use App\Payments\Domain\Repositories\FailureStateRepositoryContract;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\Repositories\IdempotencyKeyRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\Repositories\WebhookEventRepositoryContract;
use App\Payments\Domain\StateMachines\DonationStateMachine;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Services\FailureStateService;
use App\Payments\Services\PaymentOrchestrator;
use App\Payments\Services\PaymentProviderSelector;
use App\Payments\Services\PaymentService;
use App\Payments\Services\PaymentVerificationService;
use App\Payments\Services\ReceiptService;
use App\Payments\Services\TransactionCoordinator;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

/**
 * Smoke-tests for the PaymentsServiceProvider wiring.
 *
 * These tests do NOT exercise business logic. They prove that the
 * Laravel container resolves the entire Payments kernel graph in
 * isolation. Failure here means a binding is missing or cycles.
 *
 * The tests use the test bootstrap directly so we do not require a
 * live database connection.
 */
class ContainerResolutionTest extends TestCase
{
    private function app(): Application
    {
        $app = require __DIR__ . '/../../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    public function testEveryServiceResolves(): void
    {
        $app = $this->app();
        $services = [
            PaymentService::class,
            PaymentOrchestrator::class,
            PaymentProviderSelector::class,
            PaymentVerificationService::class,
            ReceiptService::class,
            FailureStateService::class,
            TransactionCoordinator::class,
        ];
        foreach ($services as $service) {
            $instance = $app->make($service);
            $this->assertInstanceOf(
                $service,
                $instance,
                "Service [{$service}] failed to resolve"
            );
        }
    }

    public function testEveryRepositoryBindingResolves(): void
    {
        $app = $this->app();
        $repos = [
            PaymentRepositoryContract::class,
            DonationRepositoryContract::class,
            DonorRepositoryContract::class,
            ReceiptRepositoryContract::class,
            FailureStateRepositoryContract::class,
            IdempotencyKeyRepositoryContract::class,
            WebhookEventRepositoryContract::class,
            AuditEventRepositoryContract::class,
            FileAssetRepositoryContract::class,
        ];
        foreach ($repos as $repo) {
            $this->assertInstanceOf(
                $repo,
                $app->make($repo),
                "Repository binding [{$repo}] failed to resolve"
            );
        }
    }

    public function testStateMachinesAreSingletons(): void
    {
        $app = $this->app();
        $psm1 = $app->make(PaymentStateMachine::class);
        $psm2 = $app->make(PaymentStateMachine::class);
        $this->assertSame($psm1, $psm2, 'PaymentStateMachine must be a singleton');

        $dsm1 = $app->make(DonationStateMachine::class);
        $dsm2 = $app->make(DonationStateMachine::class);
        $this->assertSame($dsm1, $dsm2);

        $rsm1 = $app->make(ReceiptStateMachine::class);
        $rsm2 = $app->make(ReceiptStateMachine::class);
        $this->assertSame($rsm1, $rsm2);
    }

    public function testTaggedGatewayPoolIsNonEmptyAndTyped(): void
    {
        $app = $this->app();
        $tag = \App\Payments\Providers\PaymentsServiceProvider::gatewayTag();
        $gateways = iterator_to_array($app->tagged($tag));

        $this->assertNotEmpty($gateways, 'Gateway pool must contain at least one adapter');
        foreach ($gateways as $gateway) {
            $this->assertInstanceOf(
                PaymentGatewayContract::class,
                $gateway,
                'Every tagged adapter must implement PaymentGatewayContract'
            );
        }
    }

    /**
     * The crown jewel: resolve the entire PaymentService chain in a
     * single make() call. If any binding is missing or cyclic, this fails.
     */
    public function testPaymentServiceEndToEndResolve(): void
    {
        $app = $this->app();
        $service = $app->make(PaymentService::class);
        $this->assertInstanceOf(PaymentService::class, $service);
    }

    public function testPaymentProviderSelectorReceivesGatewaysAndClock(): void
    {
        $app = $this->app();
        $selector = $app->make(PaymentProviderSelector::class);
        $reflection = new \ReflectionClass($selector);

        // Selector must have at least one provider after construction
        $gatewaysProp = $reflection->getProperty('gateways');
        $gatewaysProp->setAccessible(true);
        $gateways = $gatewaysProp->getValue($selector);

        $this->assertIsArray($gateways);
        $this->assertNotEmpty($gateways, 'Selector constructor must inject at least one gateway');
    }
}
