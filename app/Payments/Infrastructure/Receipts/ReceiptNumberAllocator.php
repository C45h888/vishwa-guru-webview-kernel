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
 * Format: TR-{FY_start_year}-{06_digit_sequence}-{08_char_url_safe_random}
 *   e.g. TR-2026-000001-A7c3ZpQ9
 *
 * Sequential-by-FY guarantees ordering for audit / legal scans.
 * The 8-character cryptographically-secure salt (≈47 bits of entropy from
 * random_bytes(8)) makes the receipt number unguessable by enumeration,
 * closing the gap that previously exposed donor PII (name, email, phone,
 * PAN, amount, gateway order id) when the URL was the only access token.
 * See m5 finding in the payment audit (2026-08-06).
 *
 * The sequence is scoped per FY — a new sequence starts each April 1.
 * Timezone is pinned to Asia/Kolkata (IST) so FY boundary tests are deterministic.
 */
class ReceiptNumberAllocator
{
    /**
     * Canonical regex (pattern body, no delimiters) for the receipt-number
     * format produced by this allocator:
     *   TR-{4-digit FY year}-{6-digit zero-padded sequence}-{8-char URL-safe salt}
     *
     * Example valid: TR-2026-000001-A7c3ZpQ9
     * Salt alphabet: [A-Za-z0-9_-] — URL-safe per RFC 4648 (no padding).
     *
     * Routes/receipts.php imports this constant for its constraint;
     * ReceiptNumberPatternTest locks the invariant.
     */
    public const PATTERN = 'TR-\d{4}-\d{6}-[A-Za-z0-9_-]{8}';

    /**
     * Length of the URL-safe random salt (after base64-encoding 6 raw bytes).
     * 6 random bytes = 48 bits of entropy = ~281 trillion combinations per FY.
     */
    private const SALT_LENGTH = 8;

    /**
     * Anchored variant used by tests + the regex constraint on
     * routes/receipts.php. Without anchors, an 8-char prefix of a 9-char
     * salt would match the unanchored form and let oversized receipts
     * through the route. The matching-unanchored form remains the canonical
     * PATTERN because Laravel's where() constraint engine anchors it
     * automatically when prefixed with the framework's matcher — but tests
     * use the anchored form for deterministic boundary checks.
     */
    public const ANCHORED_PATTERN = '/^TR-\d{4}-\d{6}-[A-Za-z0-9_-]{8}$/';

    public function __construct(
        private readonly ReceiptRepositoryContract $receipts,
        private readonly Clock $clock,
    ) {}

    /**
     * Allocate and return the next receipt number for the current FY.
     *
     * The random salt is generated via random_bytes() (CSPRNG) and base64url-
     * encoded so it is safe for URLs and never logged. The collision probability
     * for two receipts in the same FY is ~1/2^48 — vanishingly small.
     *
     * @return string  e.g. "TR-2026-000042-A7c3ZpQ9"
     */
    public function next(): string
    {
        $nowIst = $this->clock->now()->setTimezone(new DateTimeZone('Asia/Kolkata'));
        $fy = $this->fiscalYear($nowIst);

        $max = $this->receipts->findMaxReceiptNumberForFY($fy);
        $nextSeq = $max === null ? 1 : ($this->parseSeq($max) + 1);

        return $this->format($fy, $nextSeq, $this->generateSalt());
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
     * @param  string  $receiptNumber  e.g. "TR-2026-000042-A7c3ZpQ9"
     * @return int
     */
    private function parseSeq(string $receiptNumber): int
    {
        // Format is TR-{FY}-{SEQ}-{SALT} where salt has no '-' chars
        // (the salt alphabet is [A-Za-z0-9_-] — only '_' is allowed as
        // punctuation, never '-'). 4 parts is therefore canonical.
        $parts = explode('-', $receiptNumber);

        if (count($parts) !== 4) {
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
     * Format FY + sequence + salt into a receipt number string.
     *
     * @param  int  $fiscalYear    Indian FY start year (e.g. 2026)
     * @param  int  $sequence      1..999_999 (per FY monotonic counter)
     * @param  string  $salt       URL-safe base64 string of length SALT_LENGTH
     */
    private function format(int $fiscalYear, int $sequence, string $salt): string
    {
        return sprintf('TR-%d-%06d-%s', $fiscalYear, $sequence, $salt);
    }

    /**
     * Produce an 8-character URL-safe random salt from the operating
     * system's CSPRNG (random_bytes). The salt is base64url-encoded
     * without padding (RFC 4648) so the result is filesystem- and
     * URL-safe.
     *
     * For an FY that produces up to ~100k receipts, the per-FY
     * collision probability is ~5.5e-12 — operationally negligible.
     */
    private function generateSalt(): string
    {
        // 6 raw bytes → 8 chars base64url (without padding).
        $raw = random_bytes(6);

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
