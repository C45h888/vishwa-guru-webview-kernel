<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Persistence\ValueObjects\EntityId;

$donations = app(\App\Payments\Domain\Repositories\DonationRepositoryContract::class);
$payments = app(\App\Payments\Domain\Repositories\PaymentRepositoryContract::class);
$coordinator = app(\App\Payments\Services\TransactionCoordinator::class);

echo "=== Testing Transaction Flow ===\n\n";

$result = $coordinator->execute(function () use ($donations, $payments) {
    echo "[1] Creating Donation entity...\n";
    $donation = Donation::draft(
        campaignId: EntityId::fromString('campaign_01KY7J2AZ60HXMGQCVF5GERGEG'),
        donor: DonorIdentity::identified(name: 'Debug', email: 'debug@test.com', phone: null, pan: null, address: null),
        amountMinor: 10000,
        currency: Currency::INR,
        idempotencyKey: 'idem-debug-' . time(),
        id: EntityId::generate('donation'),
    );
    echo "    Donation ID: {$donation->id()->ulid()}\n";
    echo "    State: {$donation->state()->value}\n";
    echo "    isAnonymous: " . ($donation->isAnonymous() ? 'true' : 'false') . "\n";
    
    echo "\n[2] Saving Donation...\n";
    try {
        $donations->save($donation);
        echo "    ✓ Donation saved successfully\n";
    } catch (\Throwable $e) {
        echo "    ✗ Donation save FAILED: {$e->getMessage()}\n";
        throw $e; // Re-throw to abort transaction
    }
    
    echo "\n[3] Creating Payment entity...\n";
    $payment = Payment::initialize(
        donationId: $donation->id(),
        providerCode: PaymentProvider::RAZORPAY,
        amountMinor: 10000,
        currency: Currency::INR,
        idempotencyKey: 'idem-pay-debug-' . time(),
        metadata: ['gateway_order_id' => 'order-debug-' . time()],
        id: EntityId::generate('payment'),
        providerOrderId: 'order-debug-' . time(),
    );
    echo "    Payment ID: {$payment->id()->ulid()}\n";
    echo "    Status: {$payment->status()->value}\n";
    
    echo "\n[4] Saving Payment...\n";
    try {
        $payments->save($payment);
        echo "    ✓ Payment saved successfully\n";
    } catch (\Throwable $e) {
        echo "    ✗ Payment save FAILED: {$e->getMessage()}\n";
        throw $e;
    }
    
    return 'success';
});

echo "\n=== Result ===\n";
if ($result->isFailure()) {
    echo "TRANSACTION FAILED: {$result->error()}\n";
} else {
    echo "TRANSACTION SUCCESS\n";
}
