<?php

declare(strict_types=1);

namespace Tests\Unit\Mail\Workers;

use App\Mail\Client\HostingerMailClient;
use App\Mail\Contracts\ReceiptSourceContract;
use App\Mail\MailSubstrate;
use App\Mail\Workers\EmailMakingWorker;
use App\Mail\Workers\TransientTransportWorker;
use App\Mail\Workers\TransportWorker;
use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Result;
use PHPUnit\Framework\TestCase;

/**
 * Worker-boundary tests: the transient transport worker only transports,
 * the email writer only composes, the transport worker only delivers to
 * a validated recipient.
 */
final class WorkersTest extends TestCase
{
    private MailSubstrate $substrate;
    private HostingerMailClient $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(HostingerMailClient::class);
        $config = new class implements ConfigurationContract {
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'hostinger-mail.api_token' => 'test-token',
                    'hostinger-mail.dry_run' => true,
                    'hostinger-mail.sending_mailbox.resource_id' => 'ACtest',
                    'hostinger-mail.sending_mailbox.address' => 'receipts@vsrsms.in',
                    'receipts.branding.trust_name' => 'Temple Trust',
                    default => $default,
                };
            }
            public function string(string $key, string $default = ''): string { return (string) $this->get($key, $default); }
            public function integer(string $key, int $default = 0): int { return (int) $this->get($key, $default); }
            public function boolean(string $key, bool $default = false): bool { return (bool) $this->get($key, $default); }
            public function has(string $key): bool { return $this->get($key) !== null; }
            public function all(): array { return []; }
        };
        $this->substrate = new MailSubstrate($this->client, $config);
    }

    public function testTransientTransportWorkerOnlyTransports(): void
    {
        $receipt = $this->makeReceipt();
        $payload = ['document' => $this->makeDocument(), 'pdf_bytes' => '%PDF'];

        $source = $this->createMock(ReceiptSourceContract::class);
        $source->expects($this->once())
            ->method('bundleFor')
            ->with($receipt)
            ->willReturn(Result::success($payload));

        $worker = new TransientTransportWorker($source);

        self::assertSame($payload, $worker->fetch($receipt)->value());
    }

    public function testEmailMakingWorkerComposesBodyAndAttachesThePdf(): void
    {
        $worker = new EmailMakingWorker($this->substrate);

        $result = $worker->compose($this->makeDocument(), 'https://vsrsms.in/r?t=tok', '%PDF-1.4 bytes');

        self::assertTrue($result->isOk());
        $message = $result->value();
        self::assertStringContainsString('TR-2026-000042-A7c3ZpQ9', $message['subject']);
        self::assertStringContainsString('₹5,000.00', $message['text']);
        self::assertCount(1, $message['attachments']);
        self::assertSame('TR-2026-000042-A7c3ZpQ9.pdf', $message['attachments'][0]['filename']);
        self::assertSame('%PDF-1.4 bytes', base64_decode($message['attachments'][0]['content'], true));
    }

    public function testEmailMakingWorkerSkipsTheAttachmentWithoutPdf(): void
    {
        $worker = new EmailMakingWorker($this->substrate);

        $result = $worker->compose($this->makeDocument(), 'https://vsrsms.in/r?t=tok', null);

        self::assertTrue($result->isOk());
        self::assertSame([], $result->value()['attachments']);
    }

    public function testEmailMakingWorkerRequiresASignedUrl(): void
    {
        $worker = new EmailMakingWorker($this->substrate);

        self::assertTrue($worker->compose($this->makeDocument(), '', '%PDF')->isFailure());
    }

    public function testTransportWorkerRejectsInvalidRecipients(): void
    {
        $worker = new TransportWorker($this->substrate);
        $this->client->expects($this->never())->method('send');

        self::assertTrue($worker->deliver('not-an-email', 's', 't', '<p>h</p>')->isFailure());
        self::assertTrue($worker->deliver('', 's', 't', '<p>h</p>')->isFailure());
    }

    public function testTransportWorkerDeliversToTheValidatedRecipient(): void
    {
        $worker = new TransportWorker($this->substrate);

        // dry_run mode: the substrate resolves everything without sending.
        $result = $worker->deliver('priya@example.in', 's', 't', '<p>h</p>');

        self::assertTrue($result->isOk());
        self::assertTrue($result->value()['dry_run']);
    }

    // ─── Internals ─────────────────────────────────────────────────────

    private function makeReceipt(): Receipt
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
            donorEmail: 'priya@example.in',
        );
    }

    private function makeDocument(): ReceiptDocument
    {
        return new ReceiptDocument(
            receiptNumber: 'TR-2026-000042-A7c3ZpQ9',
            fyLabel: 'FY 2026-27',
            campaignTitle: 'Temple Renovation',
            donorName: 'Priya Sharma',
            donorEmail: 'priya@example.in',
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
}
