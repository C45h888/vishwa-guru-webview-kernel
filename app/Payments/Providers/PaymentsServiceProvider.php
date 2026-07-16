<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Payments\Contracts\ReceiptGenerationContract;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Donor;
use App\Payments\Domain\Entities\FailureState;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
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
use App\Payments\Infrastructure\Adapters\InMemory\InMemoryGatewayAdapter;
use App\Payments\Infrastructure\Adapters\InMemory\InMemoryProviderAdapter;
use App\Payments\Infrastructure\Adapters\InMemory\InMemoryVerificationAdapter;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalAdapter;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalClient;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalClientFactory;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalProviderAdapter;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalVerificationAdapter;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayAdapter;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayClient;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayClientFactory;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayProviderAdapter;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayVerificationAdapter;
use App\Payments\Infrastructure\Persistence\LaravelDbAdapter;
use App\Payments\Infrastructure\Repositories\AuditEventRepository;
use App\Payments\Infrastructure\Repositories\DonationRepository;
use App\Payments\Infrastructure\Repositories\DonorRepository;
use App\Payments\Infrastructure\Repositories\FailureStateRepository;
use App\Payments\Infrastructure\Repositories\FileAssetRepository;
use App\Payments\Infrastructure\Repositories\IdempotencyKeyRepository;
use App\Payments\Infrastructure\Repositories\PaymentRepository;
use App\Payments\Infrastructure\Repositories\ReceiptRepository;
use App\Payments\Infrastructure\Repositories\WebhookEventRepository;
use App\Payments\Services\FailureStateService;
use App\Payments\Services\PaymentOrchestrator;
use App\Payments\Services\PaymentProviderSelector;
use App\Payments\Services\PaymentService;
use App\Payments\Services\PaymentVerificationService;
use App\Payments\Services\ReceiptGeneration\StubReceiptGenerator;
use App\Payments\Services\ReceiptService;
use App\Payments\Services\TransactionCoordinator;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\Contracts\RepositoryRegistryContract;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Support\Clock;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

/**
 * PaymentsServiceProvider — DI wiring for the Financial Kernel.
 *
 * Binds every Domain contract to its Infrastructure implementation.
 * Tags gateway adapters by provider code so the PaymentProviderSelector
 * can iterate them at runtime. Registers 9 repository interface→impl
 * bindings. Wires RepositoryRegistry in boot().
 *
 * This is the FINAL pass of Phase 1. After this, the entire Payments
 * kernel is resolvable from the Laravel container.
 *
 * Architectural invariants enforced here:
 *   - No business logic added (all logic lives in Services/, Pass 1.3)
 *   - No SDK construction outside the wrapper classes (Pass 1.5)
 *   - One binding per interface; concrete classes are private to the
 *     container unless explicitly used elsewhere
 *   - InMemory adapters are bound unconditionally but only INCLUDED
 *     in the tagged gateway pool when env != production
 */
final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $app = $this->app;

        // ════════════════════════════════════════════════════════════════
        // AXIS C — State machines (pure-function singletons)
        // ════════════════════════════════════════════════════════════════
        $app->singleton(PaymentStateMachine::class);
        $app->singleton(DonationStateMachine::class);
        $app->singleton(ReceiptStateMachine::class);

        // ════════════════════════════════════════════════════════════════
        // AXIS A — Persistence adapter (default = LaravelDbAdapter)
        // Tests rebind to InMemoryAdapter via bootstrap test overrides.
        // ════════════════════════════════════════════════════════════════
        $app->singleton(PersistenceAdapterContract::class, LaravelDbAdapter::class);

        // ════════════════════════════════════════════════════════════════
        // AXIS A — Client factories (Razorpay + PayPal)
        // Factories own SDK credential reading; nothing else reads .env.
        // ════════════════════════════════════════════════════════════════
        $app->singleton(RazorpayClientFactory::class);
        $app->singleton(PayPalClientFactory::class);

        // ════════════════════════════════════════════════════════════════
        // AXIS A — Client wrappers (OUR type, vendor SDK never escapes)
        // ════════════════════════════════════════════════════════════════
        $app->singleton(RazorpayClient::class,
            static fn (Container $app): RazorpayClient
                => $app->make(RazorpayClientFactory::class)->create());

        $app->singleton(PayPalClient::class,
            static fn (Container $app): PayPalClient
                => $app->make(PayPalClientFactory::class)->create());

        // ════════════════════════════════════════════════════════════════
        // AXIS A — Razorpay gateway triad (Adapter, Verification, Provider)
        // ════════════════════════════════════════════════════════════════
        $app->singleton(RazorpayAdapter::class,
            static fn (Container $app): RazorpayAdapter
                => new RazorpayAdapter($app->make(RazorpayClient::class)));

        $app->singleton(RazorpayVerificationAdapter::class,
            static fn (Container $app): RazorpayVerificationAdapter
                => new RazorpayVerificationAdapter(
                    $app->make(RazorpayClient::class),
                    $app->make(ConfigurationContract::class),
                ));

        $app->singleton(RazorpayProviderAdapter::class,
            static fn (Container $app): RazorpayProviderAdapter
                => new RazorpayProviderAdapter($app->make(ConfigurationContract::class)));

        // ════════════════════════════════════════════════════════════════
        // AXIS A — PayPal gateway triad (mirror of Razorpay)
        // ════════════════════════════════════════════════════════════════
        $app->singleton(PayPalAdapter::class,
            static fn (Container $app): PayPalAdapter
                => new PayPalAdapter($app->make(PayPalClient::class)));

        $app->singleton(PayPalVerificationAdapter::class,
            static fn (Container $app): PayPalVerificationAdapter
                => new PayPalVerificationAdapter(
                    $app->make(PayPalClient::class),
                    $app->make(ConfigurationContract::class),
                ));

        $app->singleton(PayPalProviderAdapter::class,
            static fn (Container $app): PayPalProviderAdapter
                => new PayPalProviderAdapter($app->make(ConfigurationContract::class)));

        // ════════════════════════════════════════════════════════════════
        // AXIS A — InMemory adapters (test double, no factory needed)
        // Always bound — only env controls whether they appear in tags.
        // ════════════════════════════════════════════════════════════════
        $app->singleton(InMemoryGatewayAdapter::class);
        $app->singleton(InMemoryVerificationAdapter::class);
        $app->singleton(InMemoryProviderAdapter::class);

        // ════════════════════════════════════════════════════════════════
        // AXIS A — Tag gateways + providers + verifications
        // ════════════════════════════════════════════════════════════════
        $gatewayClasses = $this->envAwareGatewayClasses($app);
        $app->tag($gatewayClasses, 'payment_gateway');

        $app->tag([
            RazorpayProviderAdapter::class,
            PayPalProviderAdapter::class,
            InMemoryProviderAdapter::class,
        ], 'payment_provider');

        $app->tag([
            RazorpayVerificationAdapter::class,
            PayPalVerificationAdapter::class,
            InMemoryVerificationAdapter::class,
        ], 'payment_verification');

        // ════════════════════════════════════════════════════════════════
        // AXIS D — Repository interface → concrete bindings
        // 9 total (incl. FileAsset from Pass 1.4)
        // ════════════════════════════════════════════════════════════════
        $repoBindings = [
            PaymentRepositoryContract::class       => PaymentRepository::class,
            DonationRepositoryContract::class      => DonationRepository::class,
            DonorRepositoryContract::class         => DonorRepository::class,
            ReceiptRepositoryContract::class       => ReceiptRepository::class,
            FailureStateRepositoryContract::class  => FailureStateRepository::class,
            IdempotencyKeyRepositoryContract::class => IdempotencyKeyRepository::class,
            WebhookEventRepositoryContract::class  => WebhookEventRepository::class,
            AuditEventRepositoryContract::class    => AuditEventRepository::class,
            FileAssetRepositoryContract::class      => FileAssetRepository::class,
        ];
        foreach ($repoBindings as $contract => $impl) {
            $app->bind($contract, $impl);
        }

        // ════════════════════════════════════════════════════════════════
        // AXIS B — Services (auto-resolved unless tagged/iterable needed)
        // ════════════════════════════════════════════════════════════════
        // ReceiptGeneration → Stub in Phase 1; replaced by ReceiptRenderer in Pass 1.7
        $app->bind(ReceiptGenerationContract::class, StubReceiptGenerator::class);

        // PaymentProviderSelector requires iterable<PaymentGatewayContract>
        // which Laravel can't auto-inject → manual closure
        $app->singleton(PaymentProviderSelector::class,
            static fn (Container $app): PaymentProviderSelector
                => new PaymentProviderSelector(
                    iterator_to_array($app->tagged('payment_gateway')),
                    $app->make(Clock::class),
                ));

        // All other services auto-resolve via constructor injection
        $app->singleton(PaymentService::class);
        $app->singleton(PaymentOrchestrator::class);
        $app->singleton(PaymentVerificationService::class);
        $app->singleton(ReceiptService::class);
        $app->singleton(FailureStateService::class);
        $app->singleton(TransactionCoordinator::class);
    }

    public function boot(): void
    {
        // ════════════════════════════════════════════════════════════════
        // RepositoryRegistry: populate entity_type → repository class map
        // Used by orchestrators that look up repos by entity type at runtime.
        // ════════════════════════════════════════════════════════════════
        $registry = $this->app->make(RepositoryRegistryContract::class);
        $registry->register(Payment::ENTITY_TYPE,         PaymentRepository::class);
        $registry->register(Donation::ENTITY_TYPE,        DonationRepository::class);
        $registry->register(Donor::ENTITY_TYPE,           DonorRepository::class);
        $registry->register(Receipt::ENTITY_TYPE,         ReceiptRepository::class);
        $registry->register(FailureState::ENTITY_TYPE,    FailureStateRepository::class);
    }

    /**
     * Returns the list of PaymentGatewayContract implementations to tag
     * for the PaymentProviderSelector, filtered by env + per-provider enable flags.
     *
     * Production: only RazorpayAdapter + PayPalAdapter (if enabled)
     * Local/Testing: also InMemoryGatewayAdapter (if enabled)
     */
    private function envAwareGatewayClasses(Container $app): array
    {
        $classes = [];
        if ($this->providerEnabled($app, 'razorpay')) {
            $classes[] = RazorpayAdapter::class;
        }
        if ($this->providerEnabled($app, 'paypal')) {
            $classes[] = PayPalAdapter::class;
        }

        $env = $app->make(EnvironmentContract::class);
        if (! $env->isProduction() && $this->providerEnabled($app, 'inmemory')) {
            $classes[] = InMemoryGatewayAdapter::class;
        }

        return $classes;
    }

    private function providerEnabled(Container $app, string $provider): bool
    {
        $config = $app->make(ConfigurationContract::class);

        return (bool) $config->get("payments.providers.{$provider}.enabled", true);
    }

    /**
     * Provides the fully-qualified tag name for the payment-gateway pool.
     * Used by tests to look up the pool without hardcoding strings.
     */
    public static function gatewayTag(): string
    {
        return 'payment_gateway';
    }
}
