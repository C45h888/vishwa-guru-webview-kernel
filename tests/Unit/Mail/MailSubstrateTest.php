<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Payments\Domain\Enums\Currency;
use App\Mail\Client\HostingerMailClient;
use App\Mail\MailSubstrate;
use App\Shared\Contracts\ConfigurationContract;
use PHPUnit\Framework\TestCase;

/**
 * MailSubstrateTest — the Hostinger Mail boundary + canonical email
 * logic. Everything runs against a mocked HostingerMailClient: no network,
 * fully deterministic.
 */
final class MailSubstrateTest extends TestCase
{
    private HostingerMailClient $client;
    private ConfigurationContract $config;
    private MailSubstrate $substrate;

    /** @var array<string, mixed> */
    private array $configValues = [];

    protected function setUp(): void
    {
        $this->client = $this->createMock(HostingerMailClient::class);
        $this->configValues = [
            'hostinger-mail.api_token' => 'test-token',
            'hostinger-mail.dry_run' => false,
            'hostinger-mail.sending_mailbox.resource_id' => 'ACtest123',
            'hostinger-mail.sending_mailbox.address' => 'receipts@vsrsms.in',
            'hostinger-mail.from_display_name' => 'VSRSMS Trust',
            'hostinger-mail.webhook.secret' => 'webhook-secret',
            'hostinger-mail.webhook.signature_header' => 'X-Webhook-Signature',
            'receipts.branding.trust_name' => 'Temple Trust',
        ];
        $this->config = $this->makeConfig($this->configValues);

        $this->substrate = new MailSubstrate($this->client, $this->config);
    }

    // ─── Account / mailbox resolution ──────────────────────────────────

    public function testAccountReturnsOrderAndMailboxes(): void
    {
        $this->client->method('me')->willReturn([
            'order_resource_id' => 'ORtest',
            'mailboxes' => [['resource_id' => 'ACtest123', 'address' => 'receipts@vsrsms.in']],
        ]);

        $result = $this->substrate->account();

        self::assertTrue($result->isOk());
        self::assertSame('ORtest', $result->value()['order_resource_id']);
    }

    public function testSendingMailboxUsesConfiguredPin(): void
    {
        $result = $this->substrate->sendingMailbox();

        self::assertTrue($result->isOk());
        self::assertSame('ACtest123', $result->value()['resource_id']);
        self::assertSame('receipts@vsrsms.in', $result->value()['address']);
        $this->client->expects($this->never())->method('me');
    }

    public function testSendingMailboxFallsBackToFirstTokenMailbox(): void
    {
        $this->configValues['hostinger-mail.sending_mailbox.resource_id'] = '';
        $this->configValues['hostinger-mail.sending_mailbox.address'] = '';
        $this->config = $this->makeConfig($this->configValues);
        $this->substrate = new MailSubstrate($this->client, $this->config);

        $this->client->method('me')->willReturn([
            'order_resource_id' => 'ORtest',
            'mailboxes' => [['resource_id' => 'ACauto', 'address' => 'auto@vsrsms.in']],
        ]);

        $result = $this->substrate->sendingMailbox();

        self::assertTrue($result->isOk());
        self::assertSame('ACauto', $result->value()['resource_id']);
    }

    // ─── Send boundary ─────────────────────────────────────────────────

    public function testSendIsDisabledWithoutToken(): void
    {
        $this->configValues['hostinger-mail.api_token'] = '';
        $this->substrate = new MailSubstrate($this->client, $this->makeConfig($this->configValues));

        self::assertFalse($this->substrate->isEnabled());
        $result = $this->substrate->send(['a@b.in'], 's', 't', '<p>h</p>');

        self::assertTrue($result->isFailure());
        self::assertSame(MailSubstrate::ERR_DISABLED, $result->error());
    }

    public function testSendRejectsEmptyRecipients(): void
    {
        $result = $this->substrate->send([], 's', 't', '<p>h</p>');

        self::assertTrue($result->isFailure());
        self::assertStringContainsString(MailSubstrate::ERR_REJECTED, (string) $result->error());
    }

    public function testSendDryRunResolvesButNeverCallsTheClient(): void
    {
        $this->configValues['hostinger-mail.dry_run'] = true;
        $this->substrate = new MailSubstrate($this->client, $this->makeConfig($this->configValues));
        $this->client->expects($this->never())->method('send');

        $result = $this->substrate->send(['donor@example.in'], 's', 't', '<p>h</p>');

        self::assertTrue($result->isOk());
        self::assertTrue($result->value()['dry_run']);
        self::assertSame('receipts@vsrsms.in', $result->value()['from']);
    }

    public function testSendSuccessPassesThroughTheClient(): void
    {
        $this->client->expects($this->once())
            ->method('send')
            ->with(
                'ACtest123',
                ['donor@example.in'],
                'Subject',
                'text body',
                '<p>html</p>',
                'VSRSMS Trust',
                [],
            );

        $result = $this->substrate->send(['donor@example.in'], 'Subject', 'text body', '<p>html</p>');

        self::assertTrue($result->isOk());
        self::assertFalse($result->value()['dry_run']);
        self::assertSame('receipts@vsrsms.in', $result->value()['from']);
    }

    /**
     * @dataProvider errorTaxonomyProvider
     */
    public function testSendMapsErrorsToTheStableTaxonomy(int $httpCode, string $expected): void
    {
        $this->client->method('send')->willThrowException(
            new \Hostinger\ApiException('boom', $httpCode),
        );

        $result = $this->substrate->send(['donor@example.in'], 's', 't', '<p>h</p>');

        self::assertTrue($result->isFailure());
        self::assertSame($expected, $result->error());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function errorTaxonomyProvider(): array
    {
        return [
            'unauthorized' => [401, MailSubstrate::ERR_AUTH],
            'forbidden' => [403, MailSubstrate::ERR_AUTH],
            'rate limited' => [429, MailSubstrate::ERR_RATE_LIMITED],
            'rejected' => [422, MailSubstrate::ERR_REJECTED],
            'server error' => [500, MailSubstrate::ERR_API],
            'network' => [0, MailSubstrate::ERR_TRANSPORT],
        ];
    }

    // ─── Webhook boundaries ────────────────────────────────────────────

    public function testVerifyWebhookSignatureAcceptsHmacDigest(): void
    {
        $payload = '{"event":"message.received"}';
        $signature = hash_hmac('sha256', $payload, 'webhook-secret');

        self::assertTrue($this->substrate->verifyWebhookSignature($payload, $signature));
    }

    public function testVerifyWebhookSignatureAcceptsVerbatimSecret(): void
    {
        self::assertTrue($this->substrate->verifyWebhookSignature('{}', 'webhook-secret'));
    }

    public function testVerifyWebhookSignatureRejectsWrongSignature(): void
    {
        self::assertFalse($this->substrate->verifyWebhookSignature('{}', hash_hmac('sha256', '{}', 'wrong')));
        self::assertFalse($this->substrate->verifyWebhookSignature('{}', 'wrong-secret'));
        self::assertFalse($this->substrate->verifyWebhookSignature('{}', null));
    }

    public function testVerifyWebhookSignatureRejectsWhenUnconfigured(): void
    {
        $this->configValues['hostinger-mail.webhook.secret'] = '';
        $this->substrate = new MailSubstrate($this->client, $this->makeConfig($this->configValues));

        self::assertFalse($this->substrate->verifyWebhookSignature('{}', 'anything'));
    }

    public function testNormalizeWebhookEventMapsKnownEvent(): void
    {
        $event = $this->substrate->normalizeWebhookEvent([
            'event' => 'message.received',
            'occurred_at' => '2026-10-02T10:00:00Z',
            'mailbox' => 'receipts@vsrsms.in',
            'message' => ['id' => 'm1'],
        ]);

        self::assertSame('message.received', $event['type']);
        self::assertSame('2026-10-02T10:00:00Z', $event['occurred_at']);
        self::assertSame('receipts@vsrsms.in', $event['mailbox']);
        self::assertSame(['id' => 'm1'], $event['message']);
    }

    public function testNormalizeWebhookEventPreservesUnknownTypes(): void
    {
        $event = $this->substrate->normalizeWebhookEvent(['event' => 'future.event', 'data' => ['x' => 1]]);

        self::assertSame('future.event', $event['type']);
        self::assertSame(['x' => 1], $event['message']);
    }

    // ─── Canonical email logic ─────────────────────────────────────────

    public function testComposeReceiptEmailQuotesTheTypedDocument(): void
    {
        $email = $this->substrate->composeReceiptEmail(
            $this->makeDocument(),
            'https://vsrsms.in/receipts/TR-2026-000042-A7c3ZpQ9?t=tok123',
        );

        self::assertStringContainsString('TR-2026-000042-A7c3ZpQ9', $email['subject']);
        self::assertStringContainsString('Temple Trust', $email['subject']);
        self::assertStringContainsString('Priya Sharma', $email['text']);
        self::assertStringContainsString('₹5,000.00', $email['text']);
        self::assertStringContainsString('Temple Renovation', $email['text']);
        self::assertStringContainsString('https://vsrsms.in/receipts/TR-2026-000042-A7c3ZpQ9?t=tok123', $email['text']);
        self::assertStringContainsString('₹5,000.00', $email['html']);
    }

    public function testComposeReceiptEmailNeverLeaksPii(): void
    {
        $email = $this->substrate->composeReceiptEmail(
            $this->makeDocument(),
            'https://vsrsms.in/receipts/TR-2026-000042-A7c3ZpQ9?t=tok123',
        );

        foreach ([$email['text'], $email['html']] as $body) {
            self::assertStringNotContainsString('ABCTY1234D', $body); // donor PAN
            self::assertStringNotContainsString('12 Temple Lane', $body); // postal address
            self::assertStringNotContainsString('priya@example.in', $body); // email body is not addressed to itself
        }
    }

    // ─── Internals ─────────────────────────────────────────────────────

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

    private function makeDocument(): ReceiptDocument
    {
        return new ReceiptDocument(
            receiptNumber: 'TR-2026-000042-A7c3ZpQ9',
            fyLabel: 'FY 2026-27',
            campaignTitle: 'Temple Renovation',
            campaignDescription: '',
            donorName: 'Priya Sharma',
            donorEmail: 'priya@example.in',
            donorPan: 'ABCTY1234D',
            donorAddressBlock: "12 Temple Lane\nMumbai, MH 400001",
            amountMinor: 5_000_00,
            currency: Currency::INR,
            amountDisplay: '₹5,000.00',
            amountInWords: 'Five Thousand Rupees Only',
            paymentReference: 'payment_01ARZ3NDEKTSV4RRFFQ69G5FAV',
            paymentDate: new \DateTimeImmutable('2026-07-15T10:00:00+05:30'),
            paymentDateDisplay: '15 July 2026',
            generatedAt: new \DateTimeImmutable('2026-07-16T12:00:00+05:30'),
            issuedDateDisplay: '16 July 2026',
            isTaxDeductible: true,
            tax80gEligible: true,
            tax80gCertificateNumber: '80G/2026/AAATS1234R/AB12CD',
            tax80gRegistrationNumber: 'AAATS1234R',
            tax80gNote: null,
            trustName: 'Temple Trust',
            trustAddress: '12 Temple St',
            trustEmail: 'trust@example.com',
            trustPhone: '+91-98765-43210',
            trustPan: 'AAACT1234D',
            trustTan: 'BLRS60956A',
            trustTwelveANumber: 'S-504/12AA/CIT/MYs/2010-11',
        );
    }
}
