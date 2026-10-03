<?php

declare(strict_types=1);

namespace App\Payments\Console\Commands;

use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Donor;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\DonorRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Jobs\GenerateReceiptJob;
use App\Mail\MailSubstrate;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Console\Command;

/**
 * receipts:simulate-delivery — the canonical runtime simulation of the
 * receipt-delivery flow WITHOUT gateway payment semantics.
 *
 * What is bypassed: ONLY the gateway. The donation + payment are seeded
 * as REAL rows (the live schema enforces donor/campaign/currency FKs) and
 * the payment is walked to CAPTURED by the real PaymentStateMachine.
 * Everything downstream is the production path, unmodified:
 *
 *   GenerateReceiptJob (queue carrier)
 *     → ReceiptService::issue() → receipt substrate
 *       (DataWorker → TypesWorker → DesignWorker → PDF + row)
 *     → ReceiptEmailJob (queue carrier)
 *     → MailDispatchCoordinator seam → mail workers → Hostinger send
 *
 * Before ANY work is seeded the command asserts the CANONICAL SENDER:
 * every email in the system must leave from
 * HOSTINGER_MAIL_SENDING_MAILBOX (canonical: sriramguruji@vsrsms.in).
 * A mismatch aborts the simulation — the invariant is enforced, not
 * merely observed.
 */
final class SimulateReceiptDeliveryCommand extends Command
{
    protected $signature = 'receipts:simulate-delivery
        {--to= : Recipient email (the simulated donor)}
        {--amount=100000 : Donation amount in minor units (default ₹1,000.00)}
        {--campaign-slug= : Campaign slug to donate towards (default: first displayable campaign)}
        {--expect-from=sriramguruji@vsrsms.in : Fail unless the canonical sender resolves to this address}';

    protected $description = 'Simulate a full receipt delivery (no gateway) through the canonical queue + substrate + mail seam.';

    public function handle(
        DonationRepositoryContract $donations,
        DonorRepositoryContract $donors,
        PaymentRepositoryContract $payments,
        CampaignRepositoryContract $campaigns,
        MailSubstrate $mail,
    ): int {
        $to = (string) $this->option('to');
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('receipts:simulate-delivery: --to must be a valid email address.');

            return self::INVALID;
        }

        $amountMinor = max(1, (int) $this->option('amount'));

        // ── 1. Canonical sender assertion — BEFORE any work is seeded ──
        $mailbox = $mail->sendingMailbox();
        if ($mailbox->isFailure()) {
            $this->error('receipts:simulate-delivery: cannot resolve sending mailbox — '.(string) $mailbox->error());

            return self::FAILURE;
        }

        /** @var array{resource_id: string, address: string} $from */
        $from = $mailbox->value();
        $expectedFrom = (string) $this->option('expect-from');

        if ($from['address'] !== $expectedFrom) {
            $this->error(sprintf(
                'CANONICAL SENDER MISMATCH: resolved [%s], expected [%s]. Aborting before any mail is queued.',
                $from['address'],
                $expectedFrom,
            ));

            return self::FAILURE;
        }

        $this->info("Canonical sender OK: {$from['address']} (mailbox {$from['resource_id']})");

        // ── 2. Resolve REAL referable rows (live FK integrity) ─────────
        $campaign = $this->resolveCampaign($campaigns);
        if ($campaign === null) {
            $this->error('receipts:simulate-delivery: no campaign found — create one first or pass --campaign-slug.');

            return self::FAILURE;
        }

        $donor = $donors->findByEmail($to);
        if ($donor === null) {
            $donor = Donor::identified(
                name: 'Simulation Donor',
                email: $to,
                phone: null,
                metadata: ['simulation' => true],
            );
            $donors->save($donor);
        }

        // ── 3. Seed the donation + payment via the domain factories ────
        // The ONLY bypassed semantics are the gateway's: the payment
        // walks the real PaymentStateMachine to CAPTURED.
        $donation = Donation::draft(
            campaignId: EntityId::fromString($campaign->id),
            donor: DonorIdentity::identified(
                name: 'Simulation Donor',
                email: $to,
                phone: null,
                pan: null,
                address: null,
            ),
            amountMinor: $amountMinor,
            currency: Currency::INR,
            id: EntityId::generate('donation'),
        );
        $donations->save($donation);

        $payment = Payment::initialize(
            donationId: $donation->id(),
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: $amountMinor,
            currency: Currency::INR,
            idempotencyKey: 'simulate-'.bin2hex(random_bytes(8)),
            metadata: ['simulation' => true, 'bypass' => 'gateway'],
        );

        $machine = new PaymentStateMachine();
        $payment = $payment->transitionTo($machine, TransactionStatus::PENDING);
        $payment = $payment->transitionTo($machine, TransactionStatus::AUTHORIZED);
        $payment = $payment->transitionTo($machine, TransactionStatus::CAPTURED, [
            'amount_minor' => $amountMinor,
        ]);
        $payments->save($payment);

        // ── 4. Hand to the CANONICAL queue carrier ─────────────────────
        // From here the runtime does exactly what it does for a verified
        // gateway payment: issue → substrate → email seam → Hostinger.
        GenerateReceiptJob::dispatch(new Identifier($payment->id()->ulid()));

        $this->info('Simulation seeded and queued through the canonical pipeline:');
        $this->line('  payment  : '.$payment->id()->value());
        $this->line('  donation : '.$donation->id()->value());
        $this->line('  campaign : '.$campaign->title);
        $this->line('  amount   : '.number_format($amountMinor / 100, 2).' INR');
        $this->line('  recipient: '.$to);
        $this->line('  from     : '.$from['address']);
        $this->newLine();
        $this->line('Watch the receipts-worker: docker logs -f temple-trust-receipts-worker');
        $this->line('Expected chain: GenerateReceiptJob → issue (substrate: types→design) → ReceiptEmailJob → mail seam → send.');

        return self::SUCCESS;
    }

    private function resolveCampaign(CampaignRepositoryContract $campaigns): ?\App\Campaigns\Domain\DTOs\CampaignDetailDTO
    {
        $slug = (string) $this->option('campaign-slug');
        if ($slug !== '') {
            return $campaigns->findBySlug($slug);
        }

        $page = $campaigns->listDisplayable(1, 0);
        if ($page === []) {
            // Simulation only needs a referable row — widen past the
            // display-window filter to any real campaign.
            $page = $campaigns->listAllIncludingDrafts(1, 0);
        }

        return $page[0] ?? null;
    }
}
