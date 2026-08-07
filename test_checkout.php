<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Payments\Domain\ValueObjects\DonationIntent;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Services\PaymentService;
use App\Persistence\ValueObjects\EntityId;

echo "Testing checkout flow...\n";

$intent = new DonationIntent(
    campaignId: EntityId::fromString('campaign_01KY7J2AZ60HXMGQCVF5GERGEG'),
    donor: DonorIdentity::identified(
        name: 'Test Donor',
        email: 'test@example.com',
        phone: '+919999900000',
        pan: null,
        address: null,
    ),
    amountMinor: 10000,
    currency: Currency::INR,
    donorMessage: 'test donation',
    internalNotes: null,
    idempotencyKey: 'idem-test-' . time(),
);

$service = app(PaymentService::class);
$result = $service->initialize($intent);

if ($result->isFailure()) {
    echo "FAILED: " . $result->error() . "\n";
    exit(1);
}

$paymentResult = $result->value();
echo "SUCCESS:\n";
echo "  Order ID: " . $paymentResult->gatewayOrderId() . "\n";
echo "  Amount: " . $paymentResult->amountMinor() . " " . $paymentResult->currency()->value . "\n";
echo "  Provider: " . $paymentResult->provider()->value . "\n";
echo "  Status: " . $paymentResult->status()->value . "\n";
