<?php

declare(strict_types=1);

namespace Tests\Unit\Mail\Coordinator;

use App\Mail\Client\HostingerMailClient;
use App\Mail\Contracts\DeliveryBookkeepingContract;
use App\Mail\Contracts\ReceiptSourceContract;
use App\Mail\Coordinator\MailDispatchCoordinator;
use App\Mail\MailSubstrate;
use App\Mail\Workers\EmailMakingWorker;
use App\Mail\Workers\TransientTransportWorker;
use App\Mail\Workers\TransportWorker;
use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Result;
use PHPUnit\Framework\TestCase;

/**
 * MailDispatchCoordinatorTest — the seam's work-boundary rules and the
 * worker chain sequencing. Deterministic: real substrate + real workers,
 * mocked HostingerMailClient and mocked ports.
 */
final class MailDispatchCoordinatorTest extends TestCase
{
    private HostingerMailClient $client;
    private ReceiptSourceContract $source;
    private DeliveryBookkeepingContract $bookkeeping;
    private MailDispatchCoordinator $coordinator;

    /** @var array<string, mixed> */
    private array $configValues = [
        'hostinger-mail.api_token' => 'test-token',
        'hostinger-mail.dry_run' => false,
        'hostinger-mail.sending_mailbox.resource_id' => 'ACtest123',
        'hostinger-mail.sending_mailbox.address' => 'receipts@vsrsms.in',
        'hostinger-mail.from_display_name' => 'VSRSMS Trust',
        'hostinger-mail.webhook.secret' => 'webhook-secret',
        'receipts.branding.trust_name' => 'Temple Trust',
    ];

    protected function setUp(): void
    {
        $this->client = $this->createMock(HostingerMailClient::class);
        $this->source = $this->createMock(ReceiptSourceContract::class);
        $this->bookkeeping = $this->createMock(DeliveryBookkeepingContract::class);

        $substrate = new MailSubstrate($this->client, $this->makeConfig($this->configValues));

        $this->coordinator = new MailDispatchCoordinator(
            substrate: $substrate,
            dataWorker: new TransientTransportWorker($this->source),
            emailWorker: new EmailMakingWorker($substrate),
            transportWorker: new TransportWorker($substrate),
            bookkeeping: $this->bookkeeping,
        );
    }

    public function testDeliverReceiptRunsTheWorkerChainAndBooksDelivery(): void
    {
        $receipt = $this->makeReceipt('priya@example.in');

        $this->source->method('bundleFor')->willReturn(Result::success([
            'document' => $this->makeDocument('priya@example.in'),
            'pdf_bytes' => '%PDF-1.4 receipt-bytes',
        ]));

        $this->client->expects($this->once())
            ->method('send')
            ->with(
                'ACtest123',
                ['priya@example.in'],
                $this->stringContains('TR-2026-000042-A7c3ZpQ9'),
                $this->stringContains('Priya Sharma'),
                $this->stringContains('Priya Sharma'),
                'VSRSMS Trust',
                $this->callback(function (array $attachments): bool {
                    return count($attachments) === 1
                        && $attachments[0]['filename'] === 'TR-2026-000042-A7c3ZpQ9.pdf'
                        && base64_decode($attachments[0]['content'], true) === '%PDF-1.4 receipt-bytes';
                }),
            );
        $this->bookkeeping->expects($this->once())
            ->method('markDelivered')
            ->with($receipt, 'priya@example.in');

        $result = $this->coordinator->deliverReceipt($receipt, 'https://vsrsms.in/receipts/TR-2026-000042-A7c3ZpQ9?t=tok');

        self::assertTrue($result->isOk(), (string) $result->error());
        self::assertSame('sent', $result->value()['status']);
        self::assertSame('receipts@vsrsms.in', $result->value()['from']);
    }

    public function testAlreadyDeliveredIsANoOp(): void
    {
        $receipt = $this->makeReceipt('priya@example.in')->transitionDelivery(
            new ReceiptStateMachine(),
            StateTransitionEvent::DELIVERY_DISPATCHED,
        );

        $this->client->expects($this->never())->method('send');
        $this->source->expects($this->never())->method('bundleFor');
        $this->bookkeeping->expects($this->never())->method('markDelivered');

        $result = $this->coordinator->deliverReceipt($receipt, 'https://vsrsms.in/x?t=tok');

        self::assertTrue($result->isOk());
        self::assertSame('already_delivered', $result->value()['status']);
    }

    public function testReceiptWithoutEmailIsNeverMailed(): void
    {
        $this->client->expects($this->never())->method('send');

        $result = $this->coordinator->deliverReceipt($this->makeReceipt(null), 'https://vsrsms.in/x?t=tok');

        self::assertTrue($result->isFailure());
        self::assertStringContainsString('mail.no_recipient', (string) $result->error());
    }

    public function testSendFailureIsBookedAsFailedDelivery(): void
    {
        $receipt = $this->makeReceipt('priya@example.in');
        $this->source->method('bundleFor')->willReturn(Result::success([
            'document' => $this->makeDocument('priya@example.in'),
            'pdf_bytes' => null,
        ]));
        $this->client->method('send')->willThrowException(new \Hostinger\ApiException('down', 500));

        $this->bookkeeping->expects($this->once())
            ->method('markFailed')
            ->with($receipt, 'priya@example.in', $this->stringContains('mail.api_error'));

        $result = $this->coordinator->deliverReceipt($receipt, 'https://vsrsms.in/x?t=tok');

        self::assertTrue($result->isFailure());
        self::assertStringContainsString('mail.api_error', (string) $result->error());
    }

    public function testDisabledTransportFailsBeforeAnyWork(): void
    {
        $this->configValues['hostinger-mail.api_token'] = '';
        $substrate = new MailSubstrate($this->client, $this->makeConfig($this->configValues));
        $coordinator = new MailDispatchCoordinator(
            substrate: $substrate,
            dataWorker: new TransientTransportWorker($this->source),
            emailWorker: new EmailMakingWorker($substrate),
            transportWorker: new TransportWorker($substrate),
            bookkeeping: $this->bookkeeping,
        );

        $this->source->expects($this->never())->method('bundleFor');

        $result = $coordinator->deliverReceipt($this->makeReceipt('priya@example.in'), 'https://vsrsms.in/x?t=tok');

        self::assertTrue($result->isFailure());
        self::assertStringContainsString('mail.disabled', (string) $result->error());
    }

    // ─── Internals ─────────────────────────────────────────────────────

    private function makeReceipt(?string $donorEmail): Receipt
    {
        return Receipt::issue(
            donationId: \App\Persistence\ValueObjects\EntityId::generate('donation'),
            paymentId: \App\Persistence\ValueObjects\EntityId::generate('payment'),
            campaignId: \App\Persistence\ValueObjects\EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000042-A7c3ZpQ9',
            campaignTitleSnapshot: 'Temple Renovation',
            donorName: 'Priya Sharma',
            amountMinor: 5_000_00,
            currency: Currency::INR,
            contentHash: str_repeat('a', 64),
            donorEmail: $donorEmail,
        );
    }

    private function makeDocument(?string $donorEmail): ReceiptDocument
    {
        return new ReceiptDocument(
            receiptNumber: 'TR-2026-000042-A7c3ZpQ9',
            fyLabel: 'FY 2026-27',
            campaignTitle: 'Temple Renovation',
            donorName: 'Priya Sharma',
            donorEmail: $donorEmail,
            donorPan: null,
            donorAddressBlock: '',
            amountMinor: 5_000_00,
            currency: Currency::INR,
            amountDisplay: '₹5,000.00',
            amountInWords: 'Five Thousand Rupees Only',
            paymentReference: 'payment_x',
            paymentDate: null,
            paymentDateDisplay: '',
            generatedAt: new \DateTimeImmutable('2026-10-02T12:00:00+05:30'),
            issuedDateDisplay: '02 October 2026',
            isTaxDeductible: true,
            tax80gEligible: false,
            tax80gCertificateNumber: null,
            tax80gRegistrationNumber: null,
            tax80gNote: null,
            trustName: 'Temple Trust',
            trustAddress: '',
            trustEmail: '',
            trustPhone: '',
            trustPan: null,
        );
    }

    /** @param array<string, mixed> $values */
    private function makeConfig(array $values): ConfigurationContract
    {
        return new class($values) implements ConfigurationContract {
            /** @param array<string, mixed> $v */
            public function __construct(private array $v) {}
            public function get(string $key, mixed $default = null): mixed { return $this->v[$key] ?? $default; }
            public function string(string $key, string $default = ''): string { return (string) ($this->v[$key] ?? $default); }
            public function integer(string $key, int $default = 0): int { return (int) ($this->v[$key] ?? $default); }
            public function boolean(string $key, bool $default = false): bool { return (bool) ($this->v[$key] ?? $default); }
            public function has(string $key): bool { return array_key_exists($key, $this->v); }
            public function all(): array { return $this->v; }
        };
    }
}
