<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\InMemory;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Domain\DTOs\GatewayResponseDTO;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * In-memory test double for PaymentGatewayContract.
 *
 * Use the fluent API to configure scripted responses:
 *   $adapter = (new InMemoryGatewayAdapter())
 *       ->setNextInitializeResult(Result::success($response))
 *       ->setNextVerifyResult(Result::success(TransactionStatus::CAPTURED));
 *
 * Records every call for test assertion:
 *   $adapter->getInitializeCalls(); // [[$request, $result], ...]
 */
final class InMemoryGatewayAdapter implements PaymentGatewayContract
{
    /**
     * @var list<array{initialize: array{request: PaymentRequest, result: Result}, verify: array, capture: array, refund: array}>
     */
    private array $calls = [];

    private ?Result $nextInitializeResult = null;
    private ?Result $nextVerifyResult = null;
    private ?Result $nextCaptureResult = null;
    private ?Result $nextRefundResult = null;

    private bool $enabledFlag = true;
    private int $minimumAmountFlag = 100;
    private int $maximumAmountFlag = 99_999_999;
    private int $priorityFlag = 100;

    /**
     * Configure the result of the next initialize() call.
     *
     * @param Result<GatewayResponseDTO> $result
     */
    public function setNextInitializeResult(Result $result): self
    {
        $this->nextInitializeResult = $result;

        return $this;
    }

    /**
     * Configure the result of the next verify() call.
     *
     * @param Result<TransactionStatus> $result
     */
    public function setNextVerifyResult(Result $result): self
    {
        $this->nextVerifyResult = $result;

        return $this;
    }

    /**
     * Configure the result of the next capture() call.
     *
     * @param Result<TransactionStatus> $result
     */
    public function setNextCaptureResult(Result $result): self
    {
        $this->nextCaptureResult = $result;

        return $this;
    }

    /**
     * Configure the result of the next refund() call.
     *
     * @param Result<TransactionStatus> $result
     */
    public function setNextRefundResult(Result $result): self
    {
        $this->nextRefundResult = $result;

        return $this;
    }

    /**
     * Configure capability flags.
     */
    public function setEnabled(bool $enabled): self
    {
        $this->enabledFlag = $enabled;

        return $this;
    }

    public function setMinimumAmount(int $amount): self
    {
        $this->minimumAmountFlag = $amount;

        return $this;
    }

    public function setMaximumAmount(int $amount): self
    {
        $this->maximumAmountFlag = $amount;

        return $this;
    }

    public function setPriority(int $priority): self
    {
        $this->priorityFlag = $priority;

        return $this;
    }

    /**
     * Reset all recorded calls.
     */
    public function resetCalls(): self
    {
        $this->calls = [];

        return $this;
    }

    /**
     * @return list<array{request: PaymentRequest, result: Result}>
     */
    public function getInitializeCalls(): array
    {
        return array_column($this->calls, 'initialize');
    }

    /**
     * @return list<array{gatewayOrderId: string, gatewayPaymentId: ?string, result: Result}>
     */
    public function getVerifyCalls(): array
    {
        return array_column($this->calls, 'verify');
    }

    /**
     * @return list<array{transactionId: Identifier, amount: int, result: Result}>
     */
    public function getCaptureCalls(): array
    {
        return array_column($this->calls, 'capture');
    }

    /**
     * @return list<array{transactionId: Identifier, amount: int, result: Result}>
     */
    public function getRefundCalls(): array
    {
        return array_column($this->calls, 'refund');
    }

    public function initialize(PaymentRequest $request): Result
    {
        $result = $this->nextInitializeResult
            ?? Result::failure('InMemoryGatewayAdapter: no scripted initialize result');

        $this->calls[] = ['initialize' => ['request' => $request, 'result' => $result]];
        $this->nextInitializeResult = null;

        return $result;
    }

    public function verify(string $gatewayOrderId, ?string $gatewayPaymentId = null): Result
    {
        $result = $this->nextVerifyResult
            ?? Result::failure('InMemoryGatewayAdapter: no scripted verify result');

        $this->calls[] = [
            'verify' => [
                'gatewayOrderId' => $gatewayOrderId,
                'gatewayPaymentId' => $gatewayPaymentId,
                'result' => $result,
            ],
        ];
        $this->nextVerifyResult = null;

        return $result;
    }

    public function capture(Identifier $transactionId, int $amount): Result
    {
        $result = $this->nextCaptureResult
            ?? Result::success(TransactionStatus::CAPTURED);

        $this->calls[] = [
            'capture' => [
                'transactionId' => $transactionId,
                'amount' => $amount,
                'result' => $result,
            ],
        ];
        $this->nextCaptureResult = null;

        return $result;
    }

    public function refund(Identifier $transactionId, int $amount): Result
    {
        $result = $this->nextRefundResult
            ?? Result::success(TransactionStatus::REFUNDED);

        $this->calls[] = [
            'refund' => [
                'transactionId' => $transactionId,
                'amount' => $amount,
                'result' => $result,
            ],
        ];
        $this->nextRefundResult = null;

        return $result;
    }

    public function providerName(): string
    {
        return 'inmemory';
    }

    public function supports(Currency $currency): bool
    {
        return true; // Test double supports all currencies
    }

    public function enabled(): bool
    {
        return $this->enabledFlag;
    }

    public function minimumAmount(): int
    {
        return $this->minimumAmountFlag;
    }

    public function maximumAmount(): int
    {
        return $this->maximumAmountFlag;
    }

    public function priority(): int
    {
        return $this->priorityFlag;
    }
}
