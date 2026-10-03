<?php

declare(strict_types=1);

namespace App\Payments\Providers;

use App\Payments\Contracts\ReceiptGenerationContract;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Donor;
use App\Payments\Domain\Entities\FailureState;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Events\PaymentValidated;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\DonorRepositoryContract;
use App\Payments\Domain\Repositories\FailureStateRepositoryContract;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\Repositories\PaymentDocumentRepositoryContract;
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
// Note: PaymentsServiceProvider does NOT bind PersistenceAdapterContract —
// PersistenceServiceProvider owns that binding. Doctrine: one binding per
// interface, owned by the canonical provider.
use App\Payments\Infrastructure\Repositories\AuditEventRepository;
use App\Payments\Infrastructure\Repositories\DonationRepository;
use App\Payments\Infrastructure\Repositories\DonorRepository;
use App\Payments\Infrastructure\Repositories\FailureStateRepository;
use App\Payments\Infrastructure\Repositories\FileAssetRepository;
use App\Payments\Infrastructure\Repositories\PaymentDocumentRepository;
use App\Payments\Infrastructure\Repositories\IdempotencyKeyRepository;
use App\Payments\Infrastructure\Repositories\PaymentRepository;
use App\Payments\Infrastructure\Repositories\ReceiptRepository;
use App\Payments\Infrastructure\Repositories\WebhookEventRepository;
use App\Payments\Services\FailureStateService;
use App\Payments\Services\PaymentOrchestrator;
use App\Payments\Services\PaymentProviderSelector;
use App\Payments\Services\PaymentService;
use App\Payments\Console\Commands\ReconcileReceiptsCommand;
use App\Payments\Jobs\GenerateReceiptJob;
use App\Payments\Services\PaymentVerificationService;
use App\Payments\Services\ReceiptIssuanceCoordinator;
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
        // AXIS A — Persistence adapter binding is owned by
        // PersistenceServiceProvider. We do not re-bind it here.
        // Doctrine: one binding per interface; kernel-level wiring
        // lives in the kernel provider, not in domain providers.
        // ════════════════════════════════════════════════════════════════

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
            PaymentDocumentRepositoryContract::class => PaymentDocumentRepository::class,
        ];
        foreach ($repoBindings as $contract => $impl) {
            $app->bind($contract, $impl);
        }

        // ════════════════════════════════════════════════════════════════
        // AXIS B — Receipt pipeline (ReceiptSubstrate is the production impl)
        // ════════════════════════════════════════════════════════════════
        // PdfWrapper: bind DomPdfWrapper as the production implementation.
        // DomPdfWrapper self-resolves the barryvdh\DomPDF facade inside render()
        // (no constructor deps), so a single bind is sufficient.
        $app->bind(\App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper::class,
            \App\Payments\Infrastructure\Receipts\Pdf\DomPdfWrapper::class);

        // Receipt subsystem — singletons (stateless beyond constructor injection).
        // Generation logic lives in the ReceiptSubstrate (app/Payments/Receipts/),
        // which runs the data → types → design worker pipeline.
        $app->singleton(\App\Payments\Infrastructure\Receipts\ReceiptNumberAllocator::class);
        $app->singleton(\App\Payments\Infrastructure\Receipts\ReceiptStorage::class);
        $app->singleton(\App\Payments\Receipts\ReceiptSubstrate::class);

        // Mail package seam — the standalone mail package (app/Mail/) pulls
        // data and books delivery through these ports; the adapters live on
        // the Payments side and delegate to the predefined receipt workers
        // (ReceiptSubstrate DataWorker/TypesWorker) and ReceiptService.
        $app->bind(\App\Mail\Contracts\ReceiptSourceContract::class,
            \App\Payments\Mail\ReceiptSourceAdapter::class);
        $app->bind(\App\Mail\Contracts\DeliveryBookkeepingContract::class,
            \App\Payments\Mail\DeliveryBookkeepingAdapter::class);

        // ReceiptGeneration → ReceiptSubstrate is the production path.
        // The substrate decides whether to actually run based on
        // config('receipts.enabled'); the env var switch is RECEIPTS_ENABLED.
        $app->bind(ReceiptGenerationContract::class,
            \App\Payments\Receipts\ReceiptSubstrate::class);

        // PaymentProviderSelector requires iterable<PaymentGatewayContract>
        // which Laravel can't auto-inject → manual closure
        $app->singleton(PaymentProviderSelector::class,
            static fn (Container $app): PaymentProviderSelector
                => new PaymentProviderSelector(
                    iterator_to_array($app->tagged('payment_gateway')),
                    $app->make(Clock::class),
                ));

        // PaymentVerificationService requires iterable<PaymentVerificationContract>
        // which Laravel can't auto-inject → manual closure (same pattern).
        $app->singleton(PaymentVerificationService::class,
            static fn (Container $app): PaymentVerificationService
                => new PaymentVerificationService(
                    $app->make(PaymentRepositoryContract::class),
                    $app->make(IdempotencyKeyRepositoryContract::class),
                    $app->make(WebhookEventRepositoryContract::class),
                    $app->make(AuditEventRepositoryContract::class),
                    $app->make(Clock::class),
                    iterator_to_array($app->tagged('payment_verification')),
                ));

        // All other services auto-resolve via constructor injection
        $app->singleton(PaymentService::class);
        $app->singleton(PaymentOrchestrator::class);
        $app->singleton(ReceiptService::class);
        $app->singleton(ReceiptIssuanceCoordinator::class);
        $app->singleton(FailureStateService::class);
        $app->singleton(TransactionCoordinator::class);

        // Cross-kernel Shape A bridge: CMS consumes campaign reads from Payments.
        // Doctrine (cms-architecture.md §7): only the contract surface is
        // sanctioned; CMS never imports Payments\Services or Infrastructure
        // directly.
        $app->bind(
            \App\Payments\Contracts\CampaignQueryContract::class,
            \App\Payments\Infrastructure\Adapters\CampaignQueryAdapter::class,
        );
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

        // Cross-cutting entities (no dedicated entity classes — these are
        // table-keyed repositories used by orchestrators).
        $registry->register('idempotency_key', IdempotencyKeyRepository::class);
        $registry->register('webhook_event',   WebhookEventRepository::class);
        $registry->register('audit_event',     AuditEventRepository::class);
        $registry->register('file_asset',      FileAssetRepository::class);

        // ════════════════════════════════════════════════════════════════
        // Receipt generation binding: PaymentValidated → coordinator.
        // The coordinator dispatches GenerateReceiptJob onto the dedicated
        // `receipts` queue. This makes receipt generation a consequence of
        // payment validation, not of a specific HTTP capture path.
        // ════════════════════════════════════════════════════════════════
        $this->app->make('events')->listen(
            PaymentValidated::class,
            ReceiptIssuanceCoordinator::class,
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                ReconcileReceiptsCommand::class,
                \App\Payments\Console\Commands\SimulateReceiptDeliveryCommand::class,
            ]);
        }
    }

    /**
     * Declares all services this provider registers. Required so the
     * provider is visible to `php artisan` introspection and the container
     * optimizer. Without this, deferred loading fails silently.
     *
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [
            // State machines
            PaymentStateMachine::class,
            DonationStateMachine::class,
            ReceiptStateMachine::class,
            // Client factories
            RazorpayClientFactory::class,
            PayPalClientFactory::class,
            // Client wrappers
            RazorpayClient::class,
            PayPalClient::class,
            // Gateway adapters (Razorpay triad)
            RazorpayAdapter::class,
            RazorpayVerificationAdapter::class,
            RazorpayProviderAdapter::class,
            // Gateway adapters (PayPal triad)
            PayPalAdapter::class,
            PayPalVerificationAdapter::class,
            PayPalProviderAdapter::class,
            // Gateway adapters (InMemory triad)
            InMemoryGatewayAdapter::class,
            InMemoryVerificationAdapter::class,
            InMemoryProviderAdapter::class,
            // Repositories
            PaymentRepositoryContract::class,
            DonationRepositoryContract::class,
            DonorRepositoryContract::class,
            ReceiptRepositoryContract::class,
            FailureStateRepositoryContract::class,
            IdempotencyKeyRepositoryContract::class,
            WebhookEventRepositoryContract::class,
            AuditEventRepositoryContract::class,
            FileAssetRepositoryContract::class,
            PaymentDocumentRepositoryContract::class,
            // Services
            ReceiptGenerationContract::class,
            PaymentProviderSelector::class,
            PaymentService::class,
            PaymentOrchestrator::class,
            PaymentVerificationService::class,
            ReceiptService::class,
            ReceiptIssuanceCoordinator::class,
            GenerateReceiptJob::class,
            ReconcileReceiptsCommand::class,
            \App\Payments\Console\Commands\SimulateReceiptDeliveryCommand::class,
            FailureStateService::class,
            TransactionCoordinator::class,
            // Registry contract (we depend on it in boot())
            RepositoryRegistryContract::class,
        ];
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
