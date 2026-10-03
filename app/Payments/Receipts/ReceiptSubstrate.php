<?php

declare(strict_types=1);

namespace App\Payments\Receipts;

use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Payments\Contracts\ReceiptGenerationContract;
use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\FailureClassification;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Payments\Infrastructure\Receipts\AmountInWords;
use App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper;
use App\Payments\Infrastructure\Receipts\ReceiptNumberAllocator;
use App\Payments\Infrastructure\Receipts\ReceiptStorage;
use App\Payments\Receipts\Workers\DataWorker;
use App\Payments\Receipts\Workers\DesignWorker;
use App\Payments\Receipts\Workers\TypesWorker;
use App\Payments\Receipts\Workers\WorkerCadence;
use App\Payments\Receipts\Workers\WorkerExhaustedException;
use App\Payments\Services\FailureStateService;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Contracts\View\Factory as ViewFactory;

/**
 * ReceiptSubstrate — THE substrate. The single parent that holds all
 * receipt generation logic; the semantic work is split across the worker
 * boundaries (data → types → design) which import their needed logic
 * from here.
 *
 * Package file system:
 *
 *   app/Payments/Receipts/
 *     ReceiptSubstrate.php        ← this file (parent; all generation logic)
 *     Workers/
 *       DataWorker.php            ← pure transport of data
 *       TypesWorker.php           ← typed document assembly (uses verify80G())
 *       DesignWorker.php          ← compiles resources/views/receipts/design/
 *       WorkerCadence.php         ← timeout + retry policy per worker
 *       WorkerExhaustedException.php
 *
 * Flow (one canonical assembly — no preview clones):
 *   DataWorker transports rows → TypesWorker types them into the
 *   ReceiptDocument (80G decided by verify80G() below) → DesignWorker
 *   compiles the design → substrate persists the artifact and issues a
 *   ReceiptDraft carrying the SAME typed document, so the persisted
 *   receipt row is exactly what the design rendered.
 *
 * Implements ReceiptGenerationContract — the seam the current
 * architecture (ReceiptService / GenerateReceiptJob) already binds to.
 */
final class ReceiptSubstrate implements ReceiptGenerationContract
{
    private ?WorkerCadence $cadence = null;

    public function __construct(
        private readonly PaymentRepositoryContract $payments,
        private readonly DonationRepositoryContract $donations,
        private readonly CampaignRepositoryContract $campaigns,
        private readonly ReceiptRepositoryContract $receipts,
        private readonly ReceiptNumberAllocator $allocator,
        private readonly ReceiptStorage $storage,
        private readonly FailureStateService $failureStateService,
        private readonly ConfigurationContract $config,
        private readonly Clock $clock,
        private readonly ViewFactory $views,
        private readonly PdfWrapper $pdf,
        private readonly \App\Payments\Domain\Repositories\TrustIdentityRepositoryContract $trustIdentities,
    ) {
    }

    // ════════════════════════════════════════════════════════════════
    // ReceiptGenerationContract — the architecture seam
    // ════════════════════════════════════════════════════════════════

    /**
     * Phase 0.25 surface (unchanged shape).
     *
     * @return Result<array{receipt_number: string, issued_at: string, download_url: string, content_hash: string}>
     */
    public function generate(Identifier $transactionId): Result
    {
        $draftResult = $this->draft($transactionId);
        if ($draftResult->isFailure()) {
            return Result::failure($draftResult->error());
        }

        /** @var ReceiptDraft $draft */
        $draft = $draftResult->value();

        return Result::success([
            'receipt_number' => $draft->receiptNumber(),
            'issued_at' => $draft->issuedAt()->format(DATE_ATOM),
            'download_url' => sprintf('/receipts/%s/download', $draft->receiptNumber()),
            'content_hash' => $draft->contentHash(),
        ]);
    }

    /**
     * The canonical receipt pipeline. One assembly path: the typed
     * document produced here is BOTH what the design renders and what the
     * persisted receipt row is built from (via ReceiptDraft::document()).
     *
     * @return Result<ReceiptDraft>
     */
    public function draft(Identifier $transactionId): Result
    {
        $paymentEntityId = EntityId::fromString('payment_'.$transactionId->value());
        $dataWorker = $this->dataWorker();
        $typesWorker = $this->typesWorker();
        $designWorker = $this->designWorker();

        try {
            /** @var array<string, mixed> $data */
            $data = $this->cadence()->run('data', fn () => $dataWorker->fetch($paymentEntityId));
        } catch (WorkerExhaustedException $e) {
            return $this->fail($paymentEntityId, 'data_worker', $e->getMessage());
        }

        $payment = $data['payment'] ?? null;
        if (! $payment instanceof Payment) {
            return Result::failure("ReceiptSubstrate: payment [{$transactionId->value()}] not found");
        }
        if (! $payment->status()->isSuccessful()) {
            return Result::failure(sprintf(
                'ReceiptSubstrate: cannot issue receipt for payment in status [%s]',
                $payment->status()->value,
            ));
        }

        // Idempotency — a persisted receipt wins.
        $existing = $data['existing_receipt'] ?? null;
        if ($existing instanceof Receipt) {
            $draft = $this->draftFromExisting($existing);
            if ($draft !== null) {
                return Result::success($draft);
            }
        }

        $receiptNumber = $this->allocator->next();

        try {
            /** @var ReceiptDocument $document */
            $document = $this->cadence()->run(
                'types',
                fn () => $typesWorker->build($data, $receiptNumber),
            );
        } catch (WorkerExhaustedException $e) {
            return $this->fail($payment->id(), 'types_worker', $e->getMessage());
        }

        try {
            $pdfResult = $this->cadence()->run('design', fn () => $designWorker->compile($document));
        } catch (WorkerExhaustedException $e) {
            return $this->fail($payment->id(), 'design_worker', $e->getMessage());
        }
        if ($pdfResult->isFailure()) {
            return $this->fail($payment->id(), 'design_worker', (string) $pdfResult->error());
        }

        /** @var string $pdfBytes */
        $pdfBytes = $pdfResult->value();

        $persistResult = $this->storage->persist(
            $transactionId->value(),
            EntityId::generate('receipt')->value(),
            $pdfBytes,
            'receipt_pdf',
        );
        if ($persistResult->isFailure()) {
            return $this->fail($payment->id(), 'file_persist', (string) $persistResult->error());
        }

        /** @var \App\Payments\Domain\ValueObjects\FileAssetRecord $fileAsset */
        $fileAsset = $persistResult->value();

        return Result::success(ReceiptDraft::fromRenderer(
            transactionId: $transactionId,
            donationId: new Identifier($payment->donationId()->ulid()),
            receiptNumber: $receiptNumber,
            fileAssetId: new Identifier(
                preg_replace('/^[a-z][a-z0-9_]*_/', '', $fileAsset->id()),
            ),
            issuedAt: $this->clock->now(),
            contentHash: $fileAsset->fileHashSha256(),
            amountInWords: $document->amountInWords,
            deliveryChannel: null,
            deliveryAddress: null,
            document: $document,
        ));
    }

    public function receiptNumber(Identifier $transactionId): string
    {
        return $this->allocator->next();
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config->get('receipts.enabled', true);
    }

    // ════════════════════════════════════════════════════════════════
    // Typed-document entry points for other surfaces (web page, email)
    // ════════════════════════════════════════════════════════════════

    /**
     * The canonical typed document for a PERSISTED receipt. Every
     * non-PDF surface (web page, email, exports) reads through here so
     * they can never disagree with the rendered design.
     */
    public function documentFor(Receipt $receipt): ReceiptDocument
    {
        $dataWorker = $this->dataWorker();

        /** @var array<string, mixed> $data */
        $data = $this->cadence()->run('data', fn () => $dataWorker->fetchForReceipt($receipt));

        return $this->typesWorker()->buildForReceipt($data, $receipt);
    }

    /**
     * Render the designed PDF for a persisted receipt (download path).
     * Runs the same types → design pipeline as issuance, so the served
     * document is the canonical design of the canonical snapshot.
     *
     * @return Result<string>
     */
    public function renderPdfFor(Receipt $receipt): Result
    {
        $document = $this->documentFor($receipt);

        try {
            return $this->cadence()->run(
                'design',
                fn () => $this->designWorker()->compile($document),
            );
        } catch (WorkerExhaustedException $e) {
            return Result::failure($e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════════════
    // Generation logic — workers import these from the parent
    // ════════════════════════════════════════════════════════════════

    /**
     * 80G verification — the one certification decision in the system.
     *
     * Rules (Income Tax Act 1961 §80G, simplified):
     *   1. Only INR donations qualify.
     *   2. The trust must be 80G-registered.
     *   3. A certificate is required above the threshold (₹500 with PAN,
     *      ₹2,000 without); above threshold without PAN → not eligible.
     *   4. A provided PAN must be structurally valid.
     *
     * The trust's 80G REGISTRATION number and the per-receipt CERTIFICATE
     * number are separate outputs and are never conflated.
     *
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     certificate_required: bool,
     *     certificate_number: string|null,
     *     trust_registration_number: string|null,
     *     note: string|null,
     * }
     */
    public function verify80G(
        ?string $pan,
        int $amountMinor,
        Currency $currency,
        \DateTimeImmutable $paymentDate,
        ?\App\Payments\Domain\ValueObjects\TrustIdentity $trust = null,
    ): array {
        // DB-ONLY: the 80G registration number comes exclusively from
        // the transported trust row (DataWorker → TypesWorker). There is
        // deliberately no config/env fallback — credentials never flow
        // through env. A missing row means "registered flag on, number
        // unknown" rather than a fabricated number.
        $registration = $trust?->eightyGNumber;

        $ineligible = fn (string $reason, bool $certRequired = false, ?string $note = null): array => [
            'eligible' => false,
            'reason' => $reason,
            'certificate_required' => $certRequired,
            'certificate_number' => null,
            'trust_registration_number' => $registration,
            'note' => $note,
        ];

        if ($currency !== Currency::INR) {
            return $ineligible('80G exemption only available for INR donations');
        }

        if (! (bool) $this->config->get('receipts.80g.trust_registered', false)) {
            return $ineligible('Trust is not registered under Section 80G');
        }

        $hasPan = $pan !== null && $pan !== '';
        $threshold = $hasPan
            ? (int) $this->config->get('receipts.80g.certificate_threshold_minor', 500_00)
            : 200_00;
        $needsCertificate = $amountMinor > $threshold;

        if ($needsCertificate && ! $hasPan) {
            return $ineligible(
                'PAN is mandatory for donations exceeding the 80G certificate threshold',
                certRequired: true,
                note: 'Note: PAN details not provided. Tax deduction under Section 80G requires a valid PAN.',
            );
        }

        if ($hasPan && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/i', (string) $pan)) {
            return $ineligible('Provided PAN is not in a valid format', $needsCertificate);
        }

        $certificateNumber = null;
        if ($needsCertificate && $registration !== null) {
            $fy = (int) $paymentDate->format('Y');
            if ((int) $paymentDate->format('n') < 4) {
                $fy--;
            }
            $serial = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $certificateNumber = sprintf('80G/%d/%s/%s', $fy, $registration, $serial);
        }

        return [
            'eligible' => true,
            'reason' => null,
            'certificate_required' => $needsCertificate,
            'certificate_number' => $certificateNumber,
            'trust_registration_number' => $registration,
            'note' => (! $hasPan && $needsCertificate === false)
                ? 'Note: PAN not provided. Donations above the certificate threshold require PAN for an 80G certificate.'
                : null,
        ];
    }

    /**
     * Canonical money display — Indian digit grouping for INR
     * (₹1,00,000.00), exponent-correct everywhere else. THE single money
     * formatter for every receipt surface.
     */
    public function formatMoney(int $amountMinor, Currency $currency): string
    {
        $exponent = $currency->exponent();
        $major = intdiv($amountMinor, 10 ** $exponent);
        $minor = $amountMinor % (10 ** $exponent);
        $decimal = $exponent > 0 ? '.'.str_pad((string) $minor, $exponent, '0', STR_PAD_LEFT) : '';

        $grouped = $currency === Currency::INR
            ? $this->indianGroup($major)
            : number_format((float) $major, 0, '.', ',');

        return $currency->symbol().$grouped.$decimal;
    }

    /**
     * Canonical date display — "02 October 2026" in Asia/Kolkata. THE
     * single human date dialect for every receipt surface (machine
     * consumers get DATE_ATOM from the typed document).
     */
    public function formatDate(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('Asia/Kolkata'))->format('d F Y');
    }

    /** Canonical fiscal-year label — "FY 2026-27" (Indian FY, April start). */
    public function formatFY(\DateTimeImmutable $date): string
    {
        $year = (int) $date->format('Y');
        $fyStart = ((int) $date->format('n') >= 4) ? $year : ($year - 1);

        return sprintf('FY %d-%02d', $fyStart, ($fyStart + 1) % 100);
    }

    /**
     * Canonical postal address block (multi-line).
     *
     * @param  array<string, mixed>|null  $address
     */
    public function formatAddressBlock(?array $address): string
    {
        if ($address === null || $address === []) {
            return '';
        }

        $lines = [];
        foreach (['line1', 'line2'] as $key) {
            if (isset($address[$key]) && $address[$key] !== '') {
                $lines[] = (string) $address[$key];
            }
        }

        if (($address['city'] ?? '') !== '') {
            $cityLine = (string) $address['city'];
            if (($address['state'] ?? '') !== '') {
                $cityLine .= ', '.$address['state'];
            }
            if (($address['pincode'] ?? '') !== '') {
                $cityLine .= ' '.$address['pincode'];
            }
            $lines[] = $cityLine;
        }

        if (($address['country'] ?? '') !== '' && $address['country'] !== 'India') {
            $lines[] = (string) $address['country'];
        }

        return implode("\n", $lines);
    }

    /** Canonical amount-in-words (delegates to the shared converter). */
    public function amountInWords(int $amountMinor): string
    {
        return AmountInWords::convert($amountMinor);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->clock->now();
    }

    // ─── Donee (trust) PRESENTATION identity — config fallback ───────
    //
    // Name / address / email / phone are presentation strings, NOT
    // credentials, so a config/env fallback is legitimate here: the DB
    // row (transported by DataWorker, preferred by TypesWorker) wins,
    // these getters cover only the row-absent case (unseeded test DBs).
    //
    // Statutory CREDENTIALS (PAN, TAN, 80G + 12A numbers) have NO
    // getter here by design — they live exclusively in the DB plane
    // and reach the document only via $data['trust_identity'].

    public function trustName(): string
    {
        return (string) $this->config->get('receipts.branding.trust_name', $this->config->get('app.name', 'Temple Trust'));
    }

    public function trustAddress(): string
    {
        return (string) $this->config->get('receipts.branding.trust_address', '');
    }

    public function trustEmail(): string
    {
        return (string) $this->config->get('receipts.branding.trust_email', '');
    }

    public function trustPhone(): string
    {
        return (string) $this->config->get('receipts.branding.trust_phone', '');
    }

    // ════════════════════════════════════════════════════════════════
    // Internals
    // ════════════════════════════════════════════════════════════════

    private function draftFromExisting(Receipt $existing): ?ReceiptDraft
    {
        $receiptFileId = $existing->receiptFileId();
        if ($receiptFileId === null) {
            return null;
        }

        return ReceiptDraft::fromRenderer(
            transactionId: new Identifier($existing->paymentId()->ulid()),
            donationId: new Identifier($existing->donationId()->ulid()),
            receiptNumber: $existing->receiptNumber(),
            fileAssetId: new Identifier($receiptFileId->ulid()),
            issuedAt: $existing->generatedAt(),
            contentHash: $existing->contentHash(),
            amountInWords: $existing->amountInWords(),
            deliveryChannel: $existing->deliveryChannel(),
            deliveryAddress: $existing->deliveryAddress(),
            document: null,
        );
    }

    private function cadence(): WorkerCadence
    {
        /** @var array<string, mixed> $workers */
        $workers = (array) $this->config->get('receipts.workers', []);

        return $this->cadence ??= new WorkerCadence($workers);
    }

    private function dataWorker(): DataWorker
    {
        return new DataWorker($this->payments, $this->donations, $this->campaigns, $this->receipts, $this->trustIdentities);
    }

    private function typesWorker(): TypesWorker
    {
        return new TypesWorker($this);
    }

    private function designWorker(): DesignWorker
    {
        return new DesignWorker($this->views, $this->pdf);
    }

    private function fail(EntityId $paymentId, string $stage, string $reason): Result
    {
        try {
            $this->failureStateService->record(
                paymentId: $paymentId,
                providerCode: 'receipt_pipeline',
                gatewayOrderId: $paymentId->value(),
                observedStatus: TransactionStatus::CAPTURED,
                failureCode: "receipt.{$stage}.failed",
                failureReason: $reason,
                classification: FailureClassification::RECOVERABLE_TERMINAL,
                metadata: ['stage' => $stage],
                context: ['correlation_id' => 'receipt-'.bin2hex(random_bytes(6))],
            );
        } catch (\Throwable) {
            // FailureStateService must not throw — swallow and continue
        }

        return Result::failure($reason);
    }

    private function indianGroup(int $value): string
    {
        $digits = (string) abs($value);
        if (strlen($digits) <= 3) {
            return ($value < 0 ? '-' : '').$digits;
        }

        $last3 = substr($digits, -3);
        $rest = substr($digits, 0, -3);
        $grouped = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);

        return ($value < 0 ? '-' : '').$grouped.','.$last3;
    }
}
