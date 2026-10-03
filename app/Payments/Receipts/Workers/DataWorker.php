<?php

declare(strict_types=1);

namespace App\Payments\Receipts\Workers;

use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Domain\Repositories\TrustIdentityRepositoryContract;
use App\Payments\Domain\ValueObjects\TrustIdentity;
use App\Persistence\ValueObjects\EntityId;

/**
 * DataWorker — PURE TRANSPORT of data.
 *
 * Loads the rows the receipt pipeline needs (payment, donation, campaign,
 * any existing receipt, canonical trust identity) and hands them back
 * untyped in a plain array. No business rules, no validation, no
 * formatting — semantic decisions belong to the types worker and the
 * substrate.
 *
 * The trust_identity row is transported here (DB plane, authoritative)
 * so TypesWorker can type it into the document without ever touching
 * persistence itself — transport and generation stay separated.
 *
 * Every fetch runs under WorkerCadence (see ReceiptSubstrate) so a flaky
 * persistence layer degrades into the worker cadence instead of hanging
 * the queue worker.
 */
final class DataWorker
{
    public function __construct(
        private readonly PaymentRepositoryContract $payments,
        private readonly DonationRepositoryContract $donations,
        private readonly CampaignRepositoryContract $campaigns,
        private readonly ReceiptRepositoryContract $receipts,
        private readonly TrustIdentityRepositoryContract $trustIdentities,
    ) {
    }

    /**
     * Transport the full data bundle for a payment.
     *
     * @return array{
     *     payment: Payment|null,
     *     donation: Donation|null,
     *     campaign: object|null,
     *     existing_receipt: Receipt|null,
     *     trust_identity: TrustIdentity|null,
     * }
     */
    public function fetch(EntityId $paymentId): array
    {
        $payment = $this->payments->findById($paymentId);

        $donation = null;
        $campaign = null;
        if ($payment !== null) {
            $donation = $this->donations->findById($payment->donationId());
            if ($donation !== null) {
                $campaign = $this->campaigns->findById($donation->campaignId()->ulid());
            }
        }

        return [
            'payment' => $payment,
            'donation' => $donation,
            'campaign' => $campaign,
            'existing_receipt' => $this->receipts->findByTransactionId($paymentId),
            'trust_identity' => $this->trustIdentities->findCanonical(),
        ];
    }

    /**
     * Transport the data bundle around a persisted receipt (web page,
     * email, re-render paths).
     *
     * @return array{
     *     payment: Payment|null,
     *     donation: Donation|null,
     *     campaign: object|null,
     *     existing_receipt: Receipt|null,
     *     trust_identity: TrustIdentity|null,
     * }
     */
    public function fetchForReceipt(Receipt $receipt): array
    {
        $payment = $this->payments->findById($receipt->paymentId());

        $donation = null;
        $campaign = null;
        if ($payment !== null) {
            $donation = $this->donations->findById($payment->donationId());
        } else {
            $donation = $this->donations->findById($receipt->donationId());
        }

        if ($donation !== null) {
            $campaign = $this->campaigns->findById($donation->campaignId()->ulid());
        }

        return [
            'payment' => $payment,
            'donation' => $donation,
            'campaign' => $campaign,
            'existing_receipt' => $receipt,
            'trust_identity' => $this->trustIdentities->findCanonical(),
        ];
    }
}
