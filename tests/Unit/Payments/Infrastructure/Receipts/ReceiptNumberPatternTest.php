<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Infrastructure\Receipts\ReceiptNumberAllocator;
use PHPUnit\Framework\TestCase;

/**
 * Locks the canonical receipt-number regex to the format the
 * ReceiptNumberAllocator actually emits. The pattern was previously
 * duplicated as a literal in routes/receipts.php ([A-Z0-9]{4,32}),
 * which was looser than the allocator's output — the route accepted
 * shapes the allocator would never produce.
 *
 * m5 fix (2026-08-06): receipt numbers now include an 8-char URL-safe
 * cryptographically-random salt (e.g. TR-2026-000001-A7c3ZpQ9). The
 * sequential prefix is preserved for audit ordering, but the salt makes
 * enumeration against the public receipt route (the only access token
 * pre-auth) computationally infeasible.
 *
 * The fix: ReceiptNumberAllocator::PATTERN (single source) and
 * routes/receipts.php imports it twice. This test pins both ends
 * so any future loosening or tightening fails the build.
 */
final class ReceiptNumberPatternTest extends TestCase
{
    public function test_constant_matches_allocator_output_format(): void
    {
        // ANCHORED_PATTERN enforces the salt is exactly 8 chars. The bare
        // PATTERN constant is unanchored because Laravel's Route::where()
        // wraps it with anchors automatically — but tests want strict
        // boundary checks.
        $framed = ReceiptNumberAllocator::ANCHORED_PATTERN;

        // Representative outputs of `sprintf('TR-%d-%06d-%s', $fy, $seq, $salt)`.
        // Sequence is monotonic per-FY (1..999_999); salt is 8 URL-safe base64
        // chars from random_bytes(6) — non-deterministic, validated shape only.
        $valid = [
            'TR-2026-000001-A7c3ZpQ9',
            'TR-2026-000042-X3mKv7p2',
            'TR-2026-999999-aBcDeFgH',
            'TR-2027-000001-z_y_x-w_',     // 8-char salt mixing _ and -
            'TR-2025-000100-01234567',
            'TR-2030-500000-________',     // 8 underscores
        ];

        foreach ($valid as $value) {
            self::assertSame(
                1,
                preg_match($framed, $value),
                "Receipt number '{$value}' should match the canonical pattern.",
            );
        }
    }

    public function test_constant_rejects_shapes_the_old_loose_literal_allowed(): void
    {
        $framed = ReceiptNumberAllocator::ANCHORED_PATTERN;

        // Every one of these passed the old loose literal but cannot be
        // emitted by the allocator post-m5.
        $invalid = [
            'TR-2026-000001',                  // no salt
            'TR-2026-000042-',                 // empty salt
            'TR-2026-000042-A7c3ZpQ9X',        // salt has 9 chars (need exactly 8)
            'TR-2026-000042-A7c3Z',            // salt has 6 chars (need exactly 8)
            'TR-2026-abcde',                   // letters in the sequence slot (old shape)
            'TR-2026-12345',                   // only 5 sequence digits (need 6)
            'TR-2026-1234567',                 // 7 sequence digits (cap is 6)
            'TR-2026-000001-A7c3ZpQ=9',        // base64 padding in salt
            'TR-2026-000001-A7c3+ZpQ',         // + in salt (not URL-safe)
            'TR-2026-000001-A7c3/ZpQ',         // / in salt (not URL-safe)
            'TR-12345-000001-A7c3ZpQ9',        // 5-digit year prefix
            'tr-2026-000001-A7c3ZpQ9',         // lowercase prefix
            'TR-2026-0000000-A7c3ZpQ9',        // 7 sequence digits with leading zero
            'TR2026000001-A7c3ZpQ9',           // missing dashes
        ];

        foreach ($invalid as $value) {
            self::assertSame(
                0,
                preg_match($framed, $value),
                "Receipt number '{$value}' must NOT match the canonical pattern.",
            );
        }
    }

    public function test_routes_use_the_canonical_pattern_constant(): void
    {
        // Locate routes/receipts.php relative to the project root without
        // relying on the Laravel container's basePath() (unavailable in
        // standalone phpunit unit context pre-TestCase bootstrap).
        // tests/Unit/Payments/Infrastructure/Receipts → up 5 → /app
        $projectRoot = dirname(__DIR__, 5);
        $source = file_get_contents($projectRoot . '/routes/receipts.php');
        self::assertNotFalse($source, 'routes/receipts.php must be readable.');

        // Both route constraints must reference the constant, not a literal.
        self::assertStringContainsString(
            'ReceiptNumberAllocator::PATTERN',
            $source,
            'routes/receipts.php must reference ReceiptNumberAllocator::PATTERN, not a literal regex.',
        );

        // Both route constraints + the docblock reference. The Pattern
        // constant must appear at least 3 times: once in the docblock +
        // once for /receipts/{n} + once for /receipts/{n}/download.
        self::assertGreaterThanOrEqual(
            3,
            substr_count($source, 'ReceiptNumberAllocator::PATTERN'),
            'Both receipt routes and the docblock must reference ReceiptNumberAllocator::PATTERN.',
        );

        // The previously-loose literal must not appear anywhere.
        self::assertStringNotContainsString(
            '[A-Z0-9]{4,32}',
            $source,
            'routes/receipts.php must not contain the old loose literal "[A-Z0-9]{4,32}".',
        );
    }
}
