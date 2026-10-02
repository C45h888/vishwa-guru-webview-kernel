<?php

declare(strict_types=1);

namespace App\Mail\Client;

use Hostinger\Api\AccountApi;
use Hostinger\Api\QuotaApi;
use Hostinger\Api\SendApi;
use Hostinger\Configuration;
use Hostinger\Model\V1SendRequest;

/**
 * HostingerMailClient — the ONLY place in the codebase where the
 * Hostinger Mail API SDK (`hostinger/mail-api-php-sdk`) is constructed.
 *
 * Mirrors RazorpayClient's doctrine: one thin wrapper over the canonical
 * SDK, everything else depends on this class or the contract above it.
 * The SDK is generated from the Mail API's OpenAPI specification, so it
 * cannot drift from the API itself.
 *
 * Known SDK pitfall (documented upstream): `hostinger/mail-api-php-sdk`
 * and `hostinger/api-php-sdk` collide on the `Hostinger\` namespace
 * (Configuration, ApiException, ObjectSerializer). This project installs
 * ONLY the mail SDK; if the hosting SDK is ever added, force
 * `setHost('https://api.mail.hostinger.com')` on every Configuration.
 *
 * Non-final so unit tests can substitute a recording stub via
 * inheritance; production code resolves through DI and never sees a
 * subclass.
 */
class HostingerMailClient
{
    private Configuration $config;
    private AccountApi $accountApi;
    private SendApi $sendApi;
    private QuotaApi $quotaApi;

    public function __construct(
        private readonly string $apiToken,
        private readonly string $baseUrl = 'https://api.mail.hostinger.com',
    ) {
        $this->config = Configuration::getDefaultConfiguration()
            ->setAccessToken($this->apiToken)
            ->setHost($this->baseUrl);

        $this->accountApi = new AccountApi(config: $this->config);
        $this->sendApi = new SendApi(config: $this->config);
        $this->quotaApi = new QuotaApi(config: $this->config);
    }

    /**
     * GET /api/v1/me — the token's order + reachable mailboxes.
     *
     * @return array{order_resource_id: string, mailboxes: array<int, array{resource_id: string, address: string}>}
     * @throws \Hostinger\ApiException
     */
    public function me(): array
    {
        $response = $this->accountApi->getCurrentAccount();
        $data = $response->getData();

        $mailboxes = [];
        foreach ($data->getMailboxes() as $mailbox) {
            $mailboxes[] = [
                'resource_id' => $mailbox->getResourceId(),
                'address' => $mailbox->getAddress(),
            ];
        }

        return [
            'order_resource_id' => $data->getOrderResourceId(),
            'mailboxes' => $mailboxes,
        ];
    }

    /**
     * POST /api/v1/mailboxes/{mailboxResourceId}/send — fire-and-accepted
     * (the API returns no body on success; failures raise ApiException).
     *
     * @param  array<int, string>               $to
     * @param  array<int, array<string, mixed>> $attachments
     * @throws \Hostinger\ApiException
     */
    public function send(
        string $mailboxResourceId,
        array $to,
        string $subject,
        string $text,
        string $html,
        ?string $displayName = null,
        array $attachments = [],
    ): void {
        $request = (new V1SendRequest())
            ->setTo($to)
            ->setSubject($subject)
            ->setText($text)
            ->setHtml($html);

        if ($displayName !== null && $displayName !== '') {
            $request->setDisplayName($displayName);
        }

        if ($attachments !== []) {
            $request->setAttachments(array_map(
                static fn (array $a): \Hostinger\Model\V1SendAttachment => (new \Hostinger\Model\V1SendAttachment())
                    ->setFilename((string) ($a['filename'] ?? 'attachment'))
                    ->setContent((string) ($a['content'] ?? ''))
                    ->setContentType($a['content_type'] ?? null)
                    ->setEncoding($a['encoding'] ?? 'base64'),
                $attachments,
            ));
        }

        $this->sendApi->sendEmail($mailboxResourceId, $request);
    }

    /**
     * GET the mailbox quota (per-mailbox limits + usage).
     *
     * @return array{total_usage: int, total_limit: int, total_percentage: int, supported: bool}
     * @throws \Hostinger\ApiException
     */
    public function quota(string $mailboxResourceId): array
    {
        $response = $this->quotaApi->getQuota($mailboxResourceId);
        $data = $response->getData();

        return [
            'total_usage' => $data->getTotalUsage(),
            'total_limit' => $data->getTotalLimit(),
            'total_percentage' => $data->getTotalPercentage(),
            'supported' => $data->getSupported(),
        ];
    }
}
