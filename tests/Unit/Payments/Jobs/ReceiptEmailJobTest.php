<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Jobs;

use App\Jobs\ReceiptEmailJob;
use App\Mail\ReceiptMailable;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Unit tests for ReceiptEmailJob.
 *
 * Doctrine:
 *   - Job is idempotent on "receipt:{id}:email" within a 7d TTL.
 *   - Skips silently when the receipt has no donor email (anonymous
 *     donation — the success page URL is the donor's own path).
 *   - Fires one Mail::raw() per call with the receipt URL signed via
 *     the receipt's access_token (A3).
 *
 * Extends the framework-booting Tests\TestCase (not the bare PHPUnit
 * one) so Mail::fake() resolves the facade. The receipt repo is a
 * plain PHPUnit mock — the framework only needs to be booted for the
 * mailer, not for the rest of the orchestration.
 *
 * These tests pin the contract; future work (HTML body, attachment,
 * retry-on-bounce) extends from here.
 */
final class ReceiptEmailJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function testSkipsWhenReceiptNotFound(): void
    {
        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn(null);
        $this->app->instance(ReceiptRepositoryContract::class, $receipts);

        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $job->handle();

        Mail::assertNothingSent();
    }

    public function testSkipsWhenDonorEmailIsNull(): void
    {
        $receipt = $this->makeReceipt(donorEmail: null);

        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn($receipt);
        $this->app->instance(ReceiptRepositoryContract::class, $receipts);

        $job = new ReceiptEmailJob(new Identifier($receipt->id()->ulid()));
        $job->handle();

        Mail::assertNothingSent();
    }

    public function testSendsEmailWithSignedReceiptUrl(): void
    {
        Mail::fake();

        $receipt = $this->makeReceipt(
            donorEmail: 'priya@example.in',
            donorName: 'Priya Sharma',
        );
        $accessToken = $receipt->accessToken();
        $this->assertNotNull($accessToken);

        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn($receipt);
        $this->app->instance(ReceiptRepositoryContract::class, $receipts);

        $job = new ReceiptEmailJob(new Identifier($receipt->id()->ulid()));
        $job->handle();

        Mail::assertSentCount(1);
        Mail::assertSent(ReceiptMailable::class, function ($message) use ($receipt, $accessToken): bool {
            // Mailable::$to is a list of ['name' => .., 'address' => ..] rows.
            $recipients = array_map(
                static fn ($t): string => is_array($t) ? (string) ($t['address'] ?? '') : (string) $t,
                (array) ($message->to ?? []),
            );
            $hasRecipient = in_array('priya@example.in', $recipients, true);
            $envelope = $message->envelope();
            $subject = $envelope->subject ?? '';
            $body = $message->render() ?? '';

            return $hasRecipient
                && str_contains($subject, $receipt->receiptNumber())
                && str_contains($body, 'Priya Sharma')
                && str_contains($body, $receipt->receiptNumber())
                && str_contains($body, 't='.urlencode($accessToken))
                // PII guard — never embed the donor's PAN value or postal
                // address in the email body (the word "PAN" in guidance
                // copy is fine; the donor's VALUES are not).
                && ! str_contains($body, 'ABCPY1234D')
                && ! str_contains($body, '12 Test Lane');
        });
    }

    public function testIdempotencyKeyIsScopedToReceipt(): void
    {
        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertSame(
            'receipt:01ARZ3NDEKTSV4RRFFQ69G5FAV:email',
            $job->idempotencyKey(),
        );
    }

    public function testIsFinancialJobWithSingleAttemptAndNoBackoff(): void
    {
        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));

        // Doctrine: financial jobs fail-fast so SMTP outages surface to
        // ops instead of silently retrying.
        $this->assertSame(1, $job->tries);
        $this->assertSame([0], $job->backoff);
        $this->assertSame('receipts', $job->queue);
    }

    public function testIdempotencyTtlIsSevenDays(): void
    {
        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertSame(7 * 24 * 3600, $job->idempotencyTtl());
    }

    private function makeReceipt(
        ?string $donorEmail,
        string $donorName = 'Test Donor',
    ): Receipt {
        return Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: EntityId::generate('payment'),
            campaignId: EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000123-A7c3ZpQ9',
            campaignTitleSnapshot: 'Temple Land Acquisition',
            donorName: $donorName,
            amountMinor: 50_000,
            currency: Currency::INR,
            contentHash: str_repeat('a', 64),
            donorEmail: $donorEmail,
            donorPan: 'ABCPY1234D',
            donorAddress: ['line1' => '12 Test Lane', 'city' => 'Mumbai', 'state' => 'MH', 'pincode' => '400001'],
        );
    }
}