<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\Entities;

use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Pins the canonical public-read wire shape emitted by ReceiptController
 * via Receipt::toReadProjection(). Supersedes the previous local
 * `ReceiptSummaryProps` interface in Receipt.svelte and the broader
 * `Receipt::toArray()` (which is reserved for admin/export paths).
 */
final class ReceiptProjectionTest extends TestCase
{
    public function test_to_read_projection_emits_the_documented_keys(): void
    {
        $receipt = $this->buildReceipt();

        $projection = $receipt->toReadProjection();

        self::assertSame(
            [
                'receipt_number',
                'campaign_title_snapshot',
                'donor_name',
                'donor_email',
                'amount_minor',
                'currency_code',
                'amount_in_words',
                'is_tax_deductible',
                'tax_80g_eligible',
                'content_hash',
                'state',
                'generated_at',
            ],
            array_keys($projection),
        );
    }

    public function test_to_read_projection_does_not_leak_internal_fields(): void
    {
        $receipt = $this->buildReceipt();
        $projection = $receipt->toReadProjection();

        // The full entity `toArray()` includes internal fields; every key
        // the projection emits must be on the documented public list.
        // Catches any future field added to `toArray()` that someone
        // forgets to exclude from the projection.
        $fullKeys = array_keys($receipt->toArray());

        foreach (array_keys($projection) as $key) {
            self::assertContains(
                $key,
                [
                    'receipt_number',
                    'campaign_title_snapshot',
                    'donor_name',
                    'donor_email',
                    'amount_minor',
                    'currency_code',
                    'amount_in_words',
                    'is_tax_deductible',
                    'tax_80g_eligible',
                    'content_hash',
                    'state',
                    'generated_at',
                ],
                "Internal field '{$key}' leaked into the public-read projection. ".
                'Either narrow the projection or document why the field is public.',
            );
        }

        // The access token is the URL credential — it must never appear
        // in a wire payload.
        self::assertNotContains('access_token', array_keys($projection));
        self::assertNotEmpty($fullKeys);
    }

    public function test_to_read_projection_currency_emits_iso_code_string(): void
    {
        $receipt = $this->buildReceipt(currency: Currency::INR);
        $projection = $receipt->toReadProjection();

        self::assertSame('INR', $projection['currency_code']);
    }

    public function test_to_read_projection_generated_at_is_iso_atomic(): void
    {
        $receipt = $this->buildReceipt();
        $projection = $receipt->toReadProjection();

        self::assertIsString($projection['generated_at']);
        // DATE_ATOM = 'Y-m-d\TH:i:sP' — round-trip parse must succeed.
        $parsed = new DateTimeImmutable($projection['generated_at']);
        self::assertSame(
            $receipt->generatedAt()->format(DATE_ATOM),
            $parsed->format(DATE_ATOM),
        );
    }

    private function buildReceipt(?Currency $currency = null): Receipt
    {
        return Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: EntityId::generate('payment'),
            campaignId: EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000001',
            campaignTitleSnapshot: 'Build the temple well',
            donorName: 'Asha Devi',
            amountMinor: 12500,
            currency: $currency ?? Currency::INR,
            contentHash: str_repeat('a', 64),
            donorEmail: 'asha@example.com',
        );
    }
}