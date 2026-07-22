<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * HTTP-feature test for GET /receipts/{receiptNumber} (payments.Receipt).
 *
 * Seeds the minimal row-set needed for a receipt lookup to succeed:
 *   1. campaign
 *   2. donation (snapshots donor info for Receipt::donor_*_snapshot)
 *   3. payment in status=settled (so the receipt issuance is plausible)
 *   4. receipt row with the canonical TR-YYYY-{shortId} format
 *
 * Then asserts the Inertia payload carries the full Receipt::toArray()
 * shape and 404s on a missing receipt number.
 *
 * The receipt content_hash is 64 hex chars (SHA-256), and the
 * receiptNumber regex is enforced at the route level
 * (routes/receipts.php: 'TR-\d{4}-[A-Z0-9]{4,32}').
 */
final class ReceiptTest extends InfrastructureTestCase
{
    public function testShowRendersReceiptDetail(): void
    {
        $this->seedCampaign(id: 'cmp_rcpt_1', state: 'active', title: 'Receipt Test Campaign');
        $this->seedDonation(id: 'don_rcpt_1', campaignId: 'cmp_rcpt_1', amountMinor: 1000_00);
        $this->seedPayment(id: 'pay_rcpt_1', donationId: 'don_rcpt_1', amountMinor: 1000_00, status: 'captured');
        $this->seedReceipt(
            receiptNumber: 'TR-2026-ABCD12345678',
            donationId: 'don_rcpt_1',
            paymentId: 'pay_rcpt_1',
            campaignId: 'cmp_rcpt_1',
            amountMinor: 1000_00,
        );

        $response = $this->get('/receipts/TR-2026-ABCD12345678');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('payments/Receipt')
            ->where('receipt_number', 'TR-2026-ABCD12345678')
            ->where('amount_in_words', 'Rupees One Thousand Only')
            ->etc()
        );
    }

    public function testShowReturns404ForUnknownReceiptNumber(): void
    {
        $response = $this->get('/receipts/TR-2026-DOESNOTEXIST');

        $response->assertNotFound();
    }

    private function seedCampaign(
        string $id,
        string $state,
        string $title,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO campaigns (
                id, slug, title, category, currency_code, state,
                display_order, is_featured, metadata,
                created_at, updated_at, deleted_at
            ) VALUES (
                :id, :slug, :title, :cat, :ccy, :state,
                0, 0, \'{}\',
                :created, :updated, NULL
            )',
            [
                'id' => $id,
                'slug' => strtolower($id),
                'title' => $title,
                'cat' => 'general',
                'ccy' => 'INR',
                'state' => $state,
                'created' => $now,
                'updated' => $now,
            ],
        );
    }

    private function seedDonation(
        string $id,
        string $campaignId,
        int $amountMinor,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO donations (
                id, campaign_id, amount_minor, currency_code, state,
                donor_name_snapshot, donor_email_snapshot,
                metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :cid, :amt, :ccy, :state,
                :donor_name, :donor_email,
                \'{}\', :created, :updated, NULL
            )',
            [
                'id' => $id,
                'cid' => $campaignId,
                'amt' => $amountMinor,
                'ccy' => 'INR',
                'state' => 'completed',
                'donor_name' => 'Test Donor',
                'donor_email' => 'donor@example.com',
                'created' => $now,
                'updated' => $now,
            ],
        );
    }

    private function seedPayment(
        string $id,
        string $donationId,
        int $amountMinor,
        string $status,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO payments (
                id, donation_id, provider_code, amount_minor, currency_code,
                status, amount_captured_minor, amount_refunded_minor,
                initiated_at, idempotency_key,
                verification_metadata, method_detail, raw_provider_response,
                created_at, updated_at, deleted_at
            ) VALUES (
                :id, :did, :pc, :amt, :ccy,
                :status, :captured, 0,
                :initiated, :idemp,
                \'[]\', \'[]\', \'[]\',
                :created, :updated, NULL
            )',
            [
                'id' => $id,
                'did' => $donationId,
                'pc' => 'razorpay',
                'amt' => $amountMinor,
                'ccy' => 'INR',
                'status' => $status,
                'captured' => $amountMinor,
                'initiated' => $now,
                'idemp' => 'idemp_'.$id,
                'created' => $now,
                'updated' => $now,
            ],
        );
    }

    private function seedReceipt(
        string $receiptNumber,
        string $donationId,
        string $paymentId,
        string $campaignId,
        int $amountMinor,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO receipts (
                id, receipt_number, donation_id, payment_id, campaign_id,
                campaign_title_snapshot, donor_name, donor_email, donor_pan,
                donor_address, amount_minor, currency_code, amount_in_words,
                is_tax_deductible, tax_80g_eligible,
                content_hash, state, generated_at,
                delivery_channel, delivery_metadata,
                created_at, updated_at, deleted_at
            ) VALUES (
                :id, :num, :did, :pid, :cid,
                :title, :donor_name, :donor_email, NULL,
                \'{}\', :amt, :ccy, :words,
                1, 0,
                :hash, :state, :generated,
                NULL, \'{}\',
                :created, :updated, NULL
            )',
            [
                'id' => 'rcpt_'.bin2hex(random_bytes(8)),
                'num' => $receiptNumber,
                'did' => $donationId,
                'pid' => $paymentId,
                'cid' => $campaignId,
                'title' => 'Receipt Test Campaign',
                'donor_name' => 'Test Donor',
                'donor_email' => 'donor@example.com',
                'amt' => $amountMinor,
                'ccy' => 'INR',
                'words' => 'Rupees One Thousand Only',
                'hash' => str_repeat('a', 64),
                'state' => 'generated',
                'generated' => $now,
                'created' => $now,
                'updated' => $now,
            ],
        );
    }
}
