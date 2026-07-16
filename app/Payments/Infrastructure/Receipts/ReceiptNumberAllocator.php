<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Shared\Support\Clock;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Allocates the next sequential receipt number for the current Indian fiscal year.
 *
 * Indian FY runs April 1 → March 31.
 * Format: TR-{FY_start_year}-{06_digit_sequence}
 *   e.g. TR-2026-000001
 *
 * The sequence is scoped per FY — a new sequence starts each April 1.
 * Timezone is pinned to Asia/Kolkata (IST) so FY boundary tests are deterministic.
 */
final class ReceiptNumberAllocator
{
    public function __construct(
        private readonly ReceiptRepositoryContract $receipts,
        private readonly Clock $clock,
    ) {}

    /**
     * Allocate and return the next receipt number for the current FY.
     *
     * @return string  e.g. "TR-2026-000042"
     */
    public function next(): string
    {
        $nowIst = $this->clock->now()->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $fy = $this->fiscalYear($nowIst);

        $max = $this->receipts->findMaxReceiptNumberForFY($fy);
        $nextSeq = $max === null ? 1 : ($this->parseSeq($max) + 1);

        return $this->format($fy, $nextSeq);
    }

    /**
     * Return the fiscal year for a given datetime.
     * Indian FY starts April 1. So:
     *   April 1, 2026 → FY 2026  (year of FY start)
     *   March 31, 2027 → FY 2026  (still in FY that started April 2026)
     *   April 1, 2027 → FY 2027  (new FY starts)
     */
    public function fiscalYearFromClock(): int
    {
        $nowIst = $this->clock->now()->setTimezone(new DateTimeZone('Asia/Kolkata'));

        return $this->fiscalYear($nowIst);
    }

    /**
     * @param  \DateTimeImmutable  $nowIst  Must be Asia/Kolkata timezone
     */
    private function fiscalYear(\DateTimeImmutable $nowIst): int
    {
        $year = (int) $nowIst->format('Y');
        $month = (int) $nowIst->format('n'); // 1–12

        // April (month 4) through December → FY started this year
        // January (month 1) through March → FY started last year
        return $month >= 4 ? $year : ($year - 1);
    }

    /**
     * Extract the 6-digit sequence number from a receipt number.
     *
     * @param  string  $receiptNumber  e.g. "TR-2026-000042"
     * @return int
     */
    private function parseSeq(string $receiptNumber): int
    {
        $parts = explode('-', $receiptNumber);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException(
                "ReceiptNumberAllocator: cannot parse receipt number [{$receiptNumber}]",
            );
        }

        $seq = (int) $parts[2];

        if ($seq < 1 || $seq > 999_999) {
            throw new InvalidArgumentException(
                "ReceiptNumberAllocator: sequence out of range [{$seq}]",
            );
        }

        return $seq;
    }

    /**
     * Format FY + sequence into a receipt number string.
     */
    private function format(int $fiscalYear, int $sequence): string
    {
        return sprintf('TR-%d-%06d', $fiscalYear, $sequence);
    }
}
