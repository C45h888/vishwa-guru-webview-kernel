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
 * The fix: ReceiptNumberAllocator::PATTERN (single source) and
 * routes/receipts.php imports it twice. This test pins both ends
 * so any future loosening or tightening fails the build.
 */
final class ReceiptNumberPatternTest extends TestCase
{
    public function test_constant_matches_allocator_output_format(): void
    {
        $framed = '/' . ReceiptNumberAllocator::PATTERN . '/';

        // Representative outputs of `sprintf('TR-%d-%06d', $fy, $seq)`.
        // The lowest sequence the allocator ever emits is 1; the cap is 999_999
        // (enforced by parseSeq at ReceiptNumberAllocator.php:88-94).
        $valid = [
            'TR-2026-000001',
            'TR-2026-000042',
            'TR-2026-999999',
            'TR-2027-000001',
            'TR-2025-000100',
            'TR-2030-500000',
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
        $framed = '/' . ReceiptNumberAllocator::PATTERN . '/';

        // These all matched the old `TR-\d{4}-[A-Z0-9]{4,32}` literal but
        // cannot come out of the allocator — a tightening that the route
        // regex now mirrors.
        $invalid = [
            'TR-2026-abcde',     // letters in the sequence slot
            'TR-2026-ab12cd',    // mixed letters and digits
            'TR-2026-12345',     // only 5 sequence digits (need exactly 6)
            'TR-2026-1234567',   // 7 sequence digits (cap is 6)
            'TR-12345-000001',   // 5-digit year prefix
            'tr-2026-000001',    // lowercase prefix
            'TR-2026-0000000',   // 7 sequence digits with leading zero
            'TR2026000001',      // missing dashes
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
        $source = file_get_contents(base_path('routes/receipts.php'));
        self::assertNotFalse($source, 'routes/receipts.php must be readable.');

        // Both route constraints must reference the constant, not a literal.
        self::assertStringContainsString(
            'ReceiptNumberAllocator::PATTERN',
            $source,
            'routes/receipts.php must reference ReceiptNumberAllocator::PATTERN, not a literal regex.',
        );

        // Two references — one for /receipts/{n} and one for /receipts/{n}/download.
        self::assertSame(
            2,
            substr_count($source, 'ReceiptNumberAllocator::PATTERN'),
            'Both receipt routes must reference ReceiptNumberAllocator::PATTERN.',
        );

        // The previously-loose literal must not appear anywhere.
        self::assertStringNotContainsString(
            '[A-Z0-9]{4,32}',
            $source,
            'routes/receipts.php must not contain the old loose literal "[A-Z0-9]{4,32}".',
        );
    }
}
